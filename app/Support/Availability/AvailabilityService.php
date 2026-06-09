<?php

namespace App\Support\Availability;

use App\Enums\ScheduleExceptionType;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Calcule les créneaux réservables d'un service à une date, pour une taille de groupe,
 * selon les horaires hebdomadaires (service_schedules), les dérogations datées
 * (schedule_exceptions) et les deux plafonds de couverts :
 *  - couverts SIMULTANÉS au niveau du pool (occupation par chevauchement) ;
 *  - couverts ARRIVANT par créneau (pacing / lissage des arrivées).
 *
 * Mono-fuseau : tous les instants sont des heures murales locales (pas de conversion UTC).
 * Un créneau (slot_at) est l'arrivée alignée sur la grille absolue de l'intervalle, ce qui
 * réduit le pacing à un simple filtre d'égalité.
 */
class AvailabilityService
{
    /**
     * Disponibilités d'un restaurant à une date, par service.
     *
     * @return Collection<int, array{service: Service, slots: list<CarbonImmutable>}>
     */
    public function availability(Restaurant $restaurant, CarbonImmutable $date, int $partySize, ?Service $service = null): Collection
    {
        $services = $service !== null
            ? collect([$service])
            : $restaurant->services()->active()->orderBy('position')->orderBy('id')->get();

        // La relation restaurant est partagée : évite un rechargement par service dans
        // les méthodes effective* et dans poolReservations.
        $services->each(fn (Service $current) => $current->setRelation('restaurant', $restaurant));

        return $services
            ->map(fn (Service $current): array => [
                'service' => $current,
                'slots' => $this->availableSlots($current, $date, $partySize),
            ])
            ->values();
    }

    /**
     * Créneaux réservables d'un service à une date (filtrés par délai mini + capacité).
     *
     * @return list<CarbonImmutable>
     */
    public function availableSlots(Service $service, CarbonImmutable $date, int $partySize): array
    {
        if (! $this->isDateBookable($service->restaurant, $date)) {
            return [];
        }

        $resolved = $this->resolveWindows($service, $date);

        if ($resolved === null) {
            return [];
        }

        $candidates = $this->generateSlots($resolved['windows'], $service, $date);

        if ($candidates === []) {
            return [];
        }

        $reservations = $this->poolReservations($service, $date);
        $leadCutoff = CarbonImmutable::now()->addMinutes($service->restaurant->min_lead_time_minutes);

        return array_values(array_filter(
            $candidates,
            fn (CarbonImmutable $slot): bool => $this->passesChecks($slot, $partySize, $service, $resolved, $reservations, $leadCutoff),
        ));
    }

    /**
     * Le créneau est-il réservable maintenant ? Utilisé par l'action de création pour
     * revérifier l'appartenance à un créneau légitime ET les plafonds sur des données fraîches.
     */
    public function isSlotBookable(Service $service, CarbonImmutable $date, CarbonImmutable $slotStart, int $partySize): bool
    {
        if (! $this->isDateBookable($service->restaurant, $date)) {
            return false;
        }

        $resolved = $this->resolveWindows($service, $date);

        if ($resolved === null) {
            return false;
        }

        $candidates = $this->generateSlots($resolved['windows'], $service, $date);

        $isCandidate = collect($candidates)->contains(
            fn (CarbonImmutable $candidate): bool => $candidate->equalTo($slotStart),
        );

        if (! $isCandidate) {
            return false;
        }

        $reservations = $this->poolReservations($service, $date);
        $leadCutoff = CarbonImmutable::now()->addMinutes($service->restaurant->min_lead_time_minutes);

        return $this->passesChecks($slotStart, $partySize, $service, $resolved, $reservations, $leadCutoff);
    }

    /**
     * Résout les fenêtres réservables effectives et les plafonds pour (service, date),
     * en appliquant les dérogations par précédence. Retourne null si fermé / non offert.
     *
     * @return array{windows: list<array{opens_at: string, last_seating_at: string, closes_at: string, crosses_midnight: bool}>, max_simultaneous_covers: int, max_covers_per_slot: int}|null
     */
    public function resolveWindows(Service $service, CarbonImmutable $date): ?array
    {
        $exceptions = ScheduleException::query()
            ->where('restaurant_id', $service->restaurant_id)
            ->whereDate('date', $date->toDateString())
            ->where(function ($query) use ($service): void {
                $query->whereNull('service_id')->orWhere('service_id', $service->id);
            })
            ->get();

        // 1. Fermeture (restaurant entier ou service ciblé) → précédence maximale.
        if ($exceptions->contains(fn (ScheduleException $e): bool => $e->type === ScheduleExceptionType::Closed)) {
            return null;
        }

        // 2. Plafonds effectifs (capacité simultanée agrégée sur le pool).
        $maxSimultaneous = $this->poolCapacity($service);
        $maxPerSlot = $service->max_covers_per_slot;

        // 3. Capacité réduite (une dérogation ciblant le service prime sur celle du restaurant).
        $reduced = $this->mostSpecific($exceptions, ScheduleExceptionType::ReducedCapacity);
        if ($reduced !== null) {
            if ($reduced->capacity_override !== null) {
                $maxSimultaneous = $reduced->capacity_override;
            }
            if ($reduced->pacing_override !== null) {
                $maxPerSlot = $reduced->pacing_override;
            }
        }

        // 4. Fenêtres : des horaires spéciaux remplacent les service_schedules du jour.
        $special = $this->mostSpecific($exceptions, ScheduleExceptionType::SpecialHours);

        if ($special !== null) {
            $windows = [$this->windowFrom($special->opens_at, $special->last_seating_at, $special->closes_at, $special->crosses_midnight)];
        } else {
            $schedules = $service->schedules()->where('day_of_week', $date->dayOfWeek)->get();

            if ($schedules->isEmpty()) {
                return null;
            }

            $windows = $schedules
                ->map(fn (object $schedule): array => $this->windowFrom(
                    $schedule->opens_at,
                    $schedule->last_seating_at,
                    $schedule->closes_at,
                    (bool) $schedule->crosses_midnight,
                ))
                ->all();
        }

        return [
            'windows' => $windows,
            'max_simultaneous_covers' => (int) $maxSimultaneous,
            'max_covers_per_slot' => (int) $maxPerSlot,
        ];
    }

    /**
     * Génère les créneaux candidats (datetimes locaux) de toutes les fenêtres, sans filtre
     * de capacité ni de délai. opens_at est supposé aligné sur l'intervalle.
     *
     * @param  list<array{opens_at: string, last_seating_at: string, closes_at: string, crosses_midnight: bool}>  $windows
     * @return list<CarbonImmutable>
     */
    private function generateSlots(array $windows, Service $service, CarbonImmutable $date): array
    {
        $interval = $service->effectiveSlotInterval();
        $dateString = $date->toDateString();
        $slots = [];

        foreach ($windows as $window) {
            $start = CarbonImmutable::parse("{$dateString} {$window['opens_at']}");
            $lastSeating = CarbonImmutable::parse("{$dateString} {$window['last_seating_at']}");

            // Borne après minuit : elle appartient au lendemain (sinon plage inversée).
            if ($window['crosses_midnight'] && $window['last_seating_at'] < $window['opens_at']) {
                $lastSeating = $lastSeating->addDay();
            }

            for ($slot = $start; $slot->lessThanOrEqualTo($lastSeating); $slot = $slot->addMinutes($interval)) {
                $slots[$slot->getTimestamp()] = $slot;
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Réservations occupantes du pool autour de la date (fenêtre large bornée, indexée).
     *
     * @return Collection<int, Reservation>
     */
    private function poolReservations(Service $service, CarbonImmutable $date): Collection
    {
        $lower = CarbonImmutable::parse($date->toDateString().' 00:00:00')->subDay();
        $upper = CarbonImmutable::parse($date->toDateString().' 23:59:59')->addDay();

        return Reservation::query()
            ->where('restaurant_id', $service->restaurant_id)
            ->inPool($service->capacity_pool_key)
            ->occupying()
            ->whereBetween('reserved_at', [$lower, $upper])
            ->get(['reserved_at', 'ends_at', 'slot_at', 'party_size']);
    }

    /**
     * Vérifie délai mini + plafond simultané (pool) + plafond d'arrivées (pacing) pour un créneau.
     *
     * @param  array{windows: mixed, max_simultaneous_covers: int, max_covers_per_slot: int}  $resolved
     * @param  Collection<int, Reservation>  $reservations
     */
    private function passesChecks(CarbonImmutable $slot, int $partySize, Service $service, array $resolved, Collection $reservations, CarbonImmutable $leadCutoff): bool
    {
        if ($slot->lessThan($leadCutoff)) {
            return false;
        }

        $end = $slot->addMinutes($service->effectiveSeatingDuration());

        $occupied = 0;
        $arriving = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->reserved_at->lessThan($end) && $reservation->ends_at->greaterThan($slot)) {
                $occupied += $reservation->party_size;
            }

            if ($reservation->slot_at->equalTo($slot)) {
                $arriving += $reservation->party_size;
            }
        }

        if ($occupied + $partySize > $resolved['max_simultaneous_covers']) {
            return false;
        }

        return $arriving + $partySize <= $resolved['max_covers_per_slot'];
    }

    /**
     * La date est-elle dans la fenêtre de réservation du restaurant (ni passée, ni au-delà
     * de l'horizon) ?
     */
    private function isDateBookable(Restaurant $restaurant, CarbonImmutable $date): bool
    {
        $today = CarbonImmutable::now()->startOfDay();
        $target = CarbonImmutable::parse($date->toDateString())->startOfDay();

        return $target->greaterThanOrEqualTo($today)
            && $target->lessThanOrEqualTo($today->addDays($restaurant->booking_horizon_days));
    }

    /**
     * Capacité simultanée du pool : min des plafonds des services partageant la clé
     * (les services d'un pool DOIVENT déclarer la même valeur ; min par sécurité).
     */
    private function poolCapacity(Service $service): int
    {
        return (int) Service::query()
            ->where('restaurant_id', $service->restaurant_id)
            ->where('capacity_pool_key', $service->capacity_pool_key)
            ->min('max_simultaneous_covers');
    }

    /**
     * Sélectionne la dérogation du type donné la plus spécifique (service ciblé > restaurant entier).
     *
     * @param  Collection<int, ScheduleException>  $exceptions
     */
    private function mostSpecific(Collection $exceptions, ScheduleExceptionType $type): ?ScheduleException
    {
        return $exceptions
            ->where('type', $type)
            ->sortByDesc(fn (ScheduleException $e): int => $e->service_id !== null ? 1 : 0)
            ->first();
    }

    /**
     * @return array{opens_at: string, last_seating_at: string, closes_at: string, crosses_midnight: bool}
     */
    private function windowFrom(?string $opensAt, ?string $lastSeatingAt, ?string $closesAt, bool $crossesMidnight): array
    {
        return [
            'opens_at' => $this->normalizeTime($opensAt),
            'last_seating_at' => $this->normalizeTime($lastSeatingAt),
            'closes_at' => $this->normalizeTime($closesAt),
            'crosses_midnight' => $crossesMidnight,
        ];
    }

    /**
     * Normalise une heure en "H:i:s" (défense contre un format DB inattendu).
     */
    private function normalizeTime(?string $time): string
    {
        return Str::of((string) $time)->substr(0, 8)->padRight(8, ':00')->value();
    }
}
