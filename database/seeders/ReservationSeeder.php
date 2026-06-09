<?php

namespace Database\Seeders;

use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed de réservations aligné sur le nouveau modèle par créneau (plus de
 * ServiceAvailability ni de compteur booked_seats). Pour chaque restaurant publié
 * disposant de services avec horaires, on pose quelques réservations confirmées (et
 * quelques-unes complétées dans le passé) sur des créneaux alignés et valides, en
 * restant volontairement conservateur sur l'occupation par créneau et par salle.
 */
class ReservationSeeder extends Seeder
{
    /**
     * "Aujourd'hui" de référence du jeu de données (déterministe).
     */
    private CarbonImmutable $today;

    /**
     * Clients disponibles comme organisateurs.
     *
     * @var Collection<int, User>
     */
    private Collection $clients;

    /**
     * Couverts déjà posés par bucket d'arrivée, clé "{service_id}-{Y-m-d H:i}".
     *
     * @var array<string, int>
     */
    private array $perSlotCovers = [];

    /**
     * Couverts présents par pool sur un créneau, clé "{restaurant_id}-{pool}-{Y-m-d H:i}".
     *
     * @var array<string, int>
     */
    private array $poolCovers = [];

    /**
     * Tailles de groupe possibles (petits groupes pour rester conservateur).
     *
     * @var list<int>
     */
    private array $partySizes = [2, 2, 3, 4];

    /**
     * Quelques demandes spéciales en français.
     *
     * @var list<string>
     */
    private array $specialRequests = [
        'Table près de la fenêtre si possible.',
        'Nous fêtons un anniversaire, merci pour la petite attention.',
        'Chaise haute pour un enfant, merci.',
        'Plutôt une table calme, à l’écart si vous pouvez.',
    ];

    /**
     * Crée des réservations cohérentes pour les restaurants publiés.
     */
    public function run(): void
    {
        $this->today = CarbonImmutable::parse('2026-06-06')->startOfDay();
        $this->clients = User::role('client')->get();

        if ($this->clients->isEmpty()) {
            return;
        }

        $restaurants = Restaurant::query()
            ->where('status', RestaurantStatus::Published)
            ->with(['services' => fn ($query) => $query->where('is_active', true)->with('schedules')])
            ->orderBy('id')
            ->get();

        foreach ($restaurants as $restaurant) {
            $this->seedRestaurant($restaurant);
        }
    }

    /**
     * Pose quelques réservations pour un restaurant donné.
     */
    private function seedRestaurant(Restaurant $restaurant): void
    {
        $services = $restaurant->services
            ->filter(fn (Service $service): bool => $service->schedules->isNotEmpty());

        if ($services->isEmpty()) {
            return;
        }

        // Fenêtre de dates : quelques jours dans le passé (complétées) et quelques
        // jours proches dans le futur (confirmées), bornée par l'horizon de réservation.
        $horizon = min((int) $restaurant->booking_horizon_days, 21);

        for ($offset = -10; $offset <= $horizon; $offset += 3) {
            $date = $this->today->addDays($offset);
            $isPast = $offset < 0;

            foreach ($services as $service) {
                $service->setRelation('restaurant', $restaurant);
                $this->seedServiceDay($restaurant, $service, $date, $isPast);
            }
        }
    }

    /**
     * Pose 0 à 2 réservations sur un service un jour donné, si un horaire existe.
     */
    private function seedServiceDay(Restaurant $restaurant, Service $service, CarbonImmutable $date, bool $isPast): void
    {
        $schedule = $service->schedules
            ->firstWhere(fn ($candidate): bool => (int) $candidate->day_of_week->value === (int) $date->dayOfWeek);

        if ($schedule === null) {
            return;
        }

        $slots = $this->candidateSlots($service, $schedule, $date);

        if ($slots === []) {
            return;
        }

        $duration = $service->effectiveSeatingDuration();
        $maxPerSlot = (int) $service->max_covers_per_slot;
        $maxSimultaneous = (int) $service->max_simultaneous_covers;

        // Au plus deux réservations par (service, jour) : on reste large.
        $bookings = $isPast ? 1 : (($date->day % 2 === 0) ? 2 : 1);

        for ($index = 0; $index < $bookings; $index++) {
            $slot = $slots[($date->day + $index) % count($slots)];
            $partySize = $this->partySizes[($service->id + $index) % count($this->partySizes)];

            if (! $this->slotHasRoom($restaurant, $service, $slot, $partySize, $duration, $maxPerSlot, $maxSimultaneous)) {
                continue;
            }

            $organizer = $this->clients[($service->id + $date->day + $index) % $this->clients->count()];

            $this->createReservation($restaurant, $service, $organizer, $slot, $partySize, $duration, $isPast, $index);
            $this->reserveCapacity($restaurant, $service, $slot, $partySize, $duration);
        }
    }

    /**
     * Heures de créneaux candidates dans la fenêtre du service (opens_at, +intervalle…
     * jusqu'à last_seating_at inclus), alignées sur la grille. Hors services à cheval
     * sur minuit pour rester simple.
     *
     * @return list<CarbonImmutable>
     */
    private function candidateSlots(Service $service, $schedule, CarbonImmutable $date): array
    {
        if ($schedule->crosses_midnight) {
            return [];
        }

        $interval = $service->effectiveSlotInterval();
        $opens = $this->timeOn($date, (string) $schedule->opens_at);
        $lastSeating = $this->timeOn($date, (string) $schedule->last_seating_at);

        $slots = [];
        $cursor = $opens;

        while ($cursor->lessThanOrEqualTo($lastSeating)) {
            $slots[] = $cursor;
            $cursor = $cursor->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Compose un datetime local à partir d'une heure "HH:MM[:SS]" sur la date donnée.
     */
    private function timeOn(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $date->setTime($hour, $minute);
    }

    /**
     * Vrai si le créneau peut accueillir le groupe sans dépasser le pacing ni la salle.
     */
    private function slotHasRoom(
        Restaurant $restaurant,
        Service $service,
        CarbonImmutable $slot,
        int $partySize,
        int $duration,
        int $maxPerSlot,
        int $maxSimultaneous,
    ): bool {
        $slotKey = "{$service->id}-{$slot->format('Y-m-d H:i')}";

        if (($this->perSlotCovers[$slotKey] ?? 0) + $partySize > $maxPerSlot) {
            return false;
        }

        // Approximation conservatrice de l'occupation simultanée : couverts présents
        // sur les buckets chevauchant [slot, slot + durée).
        $present = $this->overlappingPoolCovers($restaurant, $service, $slot, $duration);

        return $present + $partySize <= $maxSimultaneous;
    }

    /**
     * Somme des couverts du même pool dont l'occupation chevauche [slot, slot + durée).
     */
    private function overlappingPoolCovers(Restaurant $restaurant, Service $service, CarbonImmutable $slot, int $duration): int
    {
        $end = $slot->addMinutes($duration);
        $present = 0;

        foreach ($this->poolCovers as $key => $covers) {
            $prefix = "{$restaurant->id}-{$service->capacity_pool_key}-";

            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            $bucket = CarbonImmutable::parse(substr($key, strlen($prefix)));

            if ($bucket->lessThan($end) && $bucket->addMinutes($duration)->greaterThan($slot)) {
                $present += $covers;
            }
        }

        return $present;
    }

    /**
     * Mémorise les couverts posés (pacing + occupation du pool).
     */
    private function reserveCapacity(Restaurant $restaurant, Service $service, CarbonImmutable $slot, int $partySize, int $duration): void
    {
        $slotKey = "{$service->id}-{$slot->format('Y-m-d H:i')}";
        $poolKey = "{$restaurant->id}-{$service->capacity_pool_key}-{$slot->format('Y-m-d H:i')}";

        $this->perSlotCovers[$slotKey] = ($this->perSlotCovers[$slotKey] ?? 0) + $partySize;
        $this->poolCovers[$poolKey] = ($this->poolCovers[$poolKey] ?? 0) + $partySize;
    }

    /**
     * Crée la réservation et son participant organisateur, comme CreateReservationAction.
     */
    private function createReservation(
        Restaurant $restaurant,
        Service $service,
        User $organizer,
        CarbonImmutable $slot,
        int $partySize,
        int $duration,
        bool $isPast,
        int $index,
    ): void {
        DB::transaction(function () use ($restaurant, $service, $organizer, $slot, $partySize, $duration, $isPast, $index): void {
            $end = $slot->addMinutes($duration);

            $status = $isPast ? ReservationStatus::Completed : ReservationStatus::Confirmed;

            // Quelques réservations futures deviennent des pré-commandes lorsque le
            // restaurant les accepte (alimente PreOrderSeeder / Payment / Order).
            $isPreorder = ! $isPast && $restaurant->accepts_preorders && $index === 0;

            $attributes = [
                'service_id' => $service->id,
                'organizer_id' => $organizer->id,
                'party_size' => $partySize,
                'reserved_at' => $slot,
                'slot_at' => $slot,
                'ends_at' => $end,
                'seating_duration_minutes' => $duration,
                'capacity_pool_key' => $service->capacity_pool_key,
                'status' => $status->value,
                'is_preorder' => $isPreorder,
                'special_requests' => $index === 0 ? $this->specialRequests[$service->id % count($this->specialRequests)] : null,
            ];

            if ($status === ReservationStatus::Completed) {
                $attributes['seated_at'] = $slot;
                $attributes['completed_at'] = $end;
            }

            /** @var Reservation $reservation */
            $reservation = $restaurant->reservations()->create($attributes);

            $reservation->participants()->create([
                'user_id' => $organizer->id,
                'role' => ParticipantRole::Organizer->value,
                'invitation_status' => InvitationStatus::Accepted->value,
                'responded_at' => now(),
                'is_attending' => true,
            ]);
        });
    }
}
