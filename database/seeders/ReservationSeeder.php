<?php

namespace Database\Seeders;

use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantStatus;
use App\Enums\ServiceType;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\ServiceAvailability;
use App\Models\ServiceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationSeeder extends Seeder
{
    /**
     * Reference "today" for the dataset.
     */
    private CarbonImmutable $today;

    /**
     * Published restaurants eligible for reservations.
     *
     * @var Collection<int, Restaurant>
     */
    private Collection $restaurants;

    /**
     * All clients available as organizers and guests.
     *
     * @var Collection<int, User>
     */
    private Collection $clients;

    /**
     * Cache of active service schedules keyed by
     * "{restaurant_id}-{day_of_week}-{service_type}".
     *
     * @var array<string, ServiceSchedule|null>
     */
    private array $scheduleCache = [];

    /**
     * Cache of schedule exceptions keyed by "{restaurant_id}-{Y-m-d}".
     *
     * @var array<string, Collection<int, ScheduleException>>
     */
    private array $exceptionCache = [];

    /**
     * Cache of service availabilities keyed by
     * "{restaurant_id}-{Y-m-d}-{service_type}".
     *
     * @var array<string, ServiceAvailability>
     */
    private array $availabilityCache = [];

    /**
     * Realistic French special requests for some reservations.
     *
     * @var list<string>
     */
    private array $specialRequests = [
        'Table près de la fenêtre si possible.',
        'Nous fêtons un anniversaire, merci pour la petite attention.',
        'Une personne en fauteuil roulant, accès PMR souhaité.',
        'Chaise haute pour un enfant, merci.',
        'Plutôt une table calme, à l’écart si vous pouvez.',
        'Allergie à l’arachide pour un convive, soyez vigilants svp.',
        'Repas d’affaires, nous aurons besoin d’un peu de tranquillité.',
        'Si possible en terrasse, sinon à l’intérieur ce sera parfait.',
    ];

    /**
     * Realistic French cancellation reasons.
     *
     * @var list<string>
     */
    private array $cancellationReasons = [
        'Empêchement de dernière minute.',
        'Changement de programme imprévu.',
        'Un des convives est souffrant.',
        'Problème de transport.',
        'Report du dîner à une autre date.',
        'Erreur sur le nombre de couverts.',
    ];

    /**
     * Seed reservations, participants and the matching availabilities.
     */
    public function run(): void
    {
        $this->today = CarbonImmutable::parse('2026-06-06');

        $this->restaurants = Restaurant::query()
            ->where('status', RestaurantStatus::Published)
            ->get();

        $this->clients = User::role('client')->get();

        if ($this->restaurants->isEmpty() || $this->clients->count() < 2) {
            return;
        }

        $faker = fake('fr_FR');
        $targetReservations = $faker->numberBetween(90, 115);

        $created = 0;
        $attempts = 0;
        $maxAttempts = $targetReservations * 8;

        while ($created < $targetReservations && $attempts < $maxAttempts) {
            $attempts++;

            if ($this->attemptReservation($faker)) {
                $created++;
            }
        }
    }

    /**
     * Try to build a single coherent reservation. Returns true on success.
     */
    private function attemptReservation(Generator $faker): bool
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->restaurants->random();

        $date = $this->randomReservationDate($faker, $restaurant);
        $serviceType = $faker->randomElement([ServiceType::Lunch, ServiceType::Dinner]);

        $schedule = $this->resolveSchedule($restaurant, $date, $serviceType);

        if ($schedule === null) {
            return false;
        }

        $exceptions = $this->resolveExceptions($restaurant, $date);
        $matchingException = $this->matchException($exceptions, $serviceType);

        if ($matchingException !== null && $matchingException->is_closed) {
            return false;
        }

        $capacity = $matchingException?->capacity ?? $schedule->capacity;
        $maxParty = $matchingException?->max_party_size ?? $schedule->max_party_size;

        if ($capacity < 1 || $maxParty < 1) {
            return false;
        }

        $availability = $this->resolveAvailability($restaurant, $date, $serviceType, $capacity, $maxParty);

        $partySize = $faker->numberBetween(1, min((int) $maxParty, 6));

        $status = $this->resolveStatus($faker, $date);
        $countsTowardCapacity = $this->statusCountsTowardCapacity($status);

        if ($countsTowardCapacity && ($availability->booked_seats + $partySize) > $availability->capacity) {
            return false;
        }

        $guestsNeeded = $partySize - 1;

        if ($guestsNeeded > ($this->clients->count() - 1)) {
            return false;
        }

        return DB::transaction(function () use (
            $faker,
            $restaurant,
            $availability,
            $date,
            $serviceType,
            $partySize,
            $status,
            $countsTowardCapacity,
            $guestsNeeded,
        ): bool {
            $organizer = $this->clients->random();

            $isPreorder = $restaurant->accepts_preorders
                && in_array($status, [
                    ReservationStatus::Confirmed,
                    ReservationStatus::Seated,
                    ReservationStatus::Completed,
                ], true)
                && $faker->boolean(50);

            $expectedArrival = $faker->boolean(70)
                ? $this->expectedArrivalTime($faker, $serviceType)
                : null;

            $attributes = [
                'public_uuid' => (string) Str::uuid(),
                'restaurant_id' => $restaurant->id,
                'service_availability_id' => $availability->id,
                'organizer_id' => $organizer->id,
                'reservation_date' => $date->toDateString(),
                'service_type' => $serviceType,
                'party_size' => $partySize,
                'status' => $status,
                'is_preorder' => $isPreorder,
                'special_requests' => $faker->boolean(30) ? $faker->randomElement($this->specialRequests) : null,
                'expected_arrival_time' => $expectedArrival,
            ];

            $this->applyStatusTimestamps($attributes, $status, $date, $serviceType, $organizer->id, $faker);

            /** @var Reservation $reservation */
            $reservation = Reservation::query()->create($attributes);

            if ($countsTowardCapacity) {
                $availability->increment('booked_seats', $partySize);
            }

            $this->createParticipants($faker, $reservation, $organizer, $guestsNeeded, $status);

            return true;
        });
    }

    /**
     * Pick a reservation date between 2026-04 (past) and the booking horizon (future).
     */
    private function randomReservationDate(Generator $faker, Restaurant $restaurant): CarbonImmutable
    {
        $start = CarbonImmutable::parse('2026-04-01');

        $horizon = $this->today->addDays(min((int) $restaurant->booking_horizon_days, 75));
        $end = $horizon->lessThan(CarbonImmutable::parse('2026-08-31'))
            ? $horizon
            : CarbonImmutable::parse('2026-08-31');

        $totalDays = max(1, $start->diffInDays($end));

        return $start->addDays($faker->numberBetween(0, (int) $totalDays))->startOfDay();
    }

    /**
     * Find the active service schedule for the restaurant on the given date/service.
     */
    private function resolveSchedule(Restaurant $restaurant, CarbonImmutable $date, ServiceType $serviceType): ?ServiceSchedule
    {
        $dayOfWeek = (int) $date->dayOfWeek; // Carbon: 0 = Sunday .. 6 = Saturday
        $key = "{$restaurant->id}-{$dayOfWeek}-{$serviceType->value}";

        if (! array_key_exists($key, $this->scheduleCache)) {
            $this->scheduleCache[$key] = ServiceSchedule::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('service_type', $serviceType->value)
                ->where('is_active', true)
                ->first();
        }

        return $this->scheduleCache[$key];
    }

    /**
     * Load schedule exceptions for the restaurant on the given date.
     *
     * @return Collection<int, ScheduleException>
     */
    private function resolveExceptions(Restaurant $restaurant, CarbonImmutable $date): Collection
    {
        $key = "{$restaurant->id}-{$date->toDateString()}";

        if (! array_key_exists($key, $this->exceptionCache)) {
            $this->exceptionCache[$key] = ScheduleException::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereDate('date', $date->toDateString())
                ->get();
        }

        return $this->exceptionCache[$key];
    }

    /**
     * Resolve the most specific exception (service-scoped first, then whole-day).
     *
     * @param  Collection<int, ScheduleException>  $exceptions
     */
    private function matchException(Collection $exceptions, ServiceType $serviceType): ?ScheduleException
    {
        $serviceScoped = $exceptions->firstWhere('service_type', $serviceType);

        if ($serviceScoped !== null) {
            return $serviceScoped;
        }

        return $exceptions->firstWhere('service_type', null);
    }

    /**
     * Get or create the service availability bucket for the slot.
     */
    private function resolveAvailability(
        Restaurant $restaurant,
        CarbonImmutable $date,
        ServiceType $serviceType,
        int $capacity,
        int $maxParty,
    ): ServiceAvailability {
        $key = "{$restaurant->id}-{$date->toDateString()}-{$serviceType->value}";

        if (! array_key_exists($key, $this->availabilityCache)) {
            $this->availabilityCache[$key] = ServiceAvailability::query()->firstOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'date' => $date->toDateString(),
                    'service_type' => $serviceType->value,
                ],
                [
                    'capacity' => $capacity,
                    'booked_seats' => 0,
                    'max_party_size' => $maxParty,
                ],
            );
        }

        return $this->availabilityCache[$key];
    }

    /**
     * Decide a status weighted by whether the date is in the past or future.
     */
    private function resolveStatus(Generator $faker, CarbonImmutable $date): ReservationStatus
    {
        $isPast = $date->lessThan($this->today->startOfDay());

        if ($isPast) {
            $roll = $faker->numberBetween(1, 100);

            return match (true) {
                $roll <= 78 => ReservationStatus::Completed,
                $roll <= 88 => ReservationStatus::NoShow,
                default => ReservationStatus::Cancelled,
            };
        }

        return $faker->numberBetween(1, 100) <= 85
            ? ReservationStatus::Confirmed
            : ReservationStatus::Cancelled;
    }

    /**
     * Statuses that consume seats from the availability bucket.
     */
    private function statusCountsTowardCapacity(ReservationStatus $status): bool
    {
        return in_array($status, [
            ReservationStatus::Confirmed,
            ReservationStatus::Seated,
            ReservationStatus::Completed,
            ReservationStatus::NoShow,
        ], true);
    }

    /**
     * Fill the status-driven timestamps and cancellation metadata.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyStatusTimestamps(
        array &$attributes,
        ReservationStatus $status,
        CarbonImmutable $date,
        ServiceType $serviceType,
        int $organizerId,
        Generator $faker,
    ): void {
        $serviceStart = $serviceType === ServiceType::Lunch ? 12 : 20;
        $seatedAt = $date->setTime($serviceStart, $faker->randomElement([0, 15, 30]));

        if ($status === ReservationStatus::Completed) {
            $attributes['seated_at'] = $seatedAt;
            $attributes['completed_at'] = $seatedAt->addMinutes($faker->numberBetween(75, 150));

            return;
        }

        if ($status === ReservationStatus::Seated) {
            $attributes['seated_at'] = $seatedAt;

            return;
        }

        if ($status === ReservationStatus::Cancelled) {
            $attributes['cancelled_at'] = $this->cancellationMoment($faker, $date);
            $attributes['cancelled_by_id'] = $organizerId;
            $attributes['cancellation_reason'] = $faker->randomElement($this->cancellationReasons);
        }
    }

    /**
     * A plausible cancellation timestamp before the reservation date.
     */
    private function cancellationMoment(Generator $faker, CarbonImmutable $date): Carbon
    {
        $candidate = $date->subDays($faker->numberBetween(0, 3))
            ->setTime($faker->numberBetween(8, 22), $faker->randomElement([0, 15, 30, 45]));

        if ($candidate->greaterThan($this->today)) {
            $candidate = $this->today->subDays($faker->numberBetween(0, 2))
                ->setTime($faker->numberBetween(8, 22), 0);
        }

        return Carbon::instance($candidate->toDateTime());
    }

    /**
     * Expected arrival within the service window.
     */
    private function expectedArrivalTime(Generator $faker, ServiceType $serviceType): string
    {
        $hour = $serviceType === ServiceType::Lunch
            ? $faker->numberBetween(12, 13)
            : $faker->numberBetween(19, 21);

        $minute = $faker->randomElement([0, 15, 30, 45]);

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    /**
     * Create the organizer participant and the distinct guest participants.
     */
    private function createParticipants(
        Generator $faker,
        Reservation $reservation,
        User $organizer,
        int $guestsNeeded,
        ReservationStatus $status,
    ): void {
        $isCompleted = $status === ReservationStatus::Completed;

        ReservationParticipant::query()->create([
            'reservation_id' => $reservation->id,
            'user_id' => $organizer->id,
            'role' => ParticipantRole::Organizer,
            'invitation_status' => InvitationStatus::Accepted,
            'responded_at' => $reservation->created_at ?? now(),
            'is_attending' => $isCompleted ? true : null,
        ]);

        if ($guestsNeeded < 1) {
            return;
        }

        $guests = $this->clients
            ->reject(fn (User $client): bool => $client->id === $organizer->id)
            ->shuffle()
            ->take($guestsNeeded);

        foreach ($guests as $guest) {
            $invitationStatus = $this->resolveInvitationStatus($faker);

            $respondedAt = $invitationStatus === InvitationStatus::Pending
                ? null
                : ($reservation->created_at ?? now());

            $isAttending = match (true) {
                $isCompleted && $invitationStatus === InvitationStatus::Accepted => true,
                $invitationStatus === InvitationStatus::Declined => false,
                default => null,
            };

            ReservationParticipant::query()->create([
                'reservation_id' => $reservation->id,
                'user_id' => $guest->id,
                'role' => ParticipantRole::Guest,
                'invitation_status' => $invitationStatus,
                'responded_at' => $respondedAt,
                'is_attending' => $isAttending,
            ]);
        }
    }

    /**
     * Weighted guest invitation status: mostly accepted, some pending/declined.
     */
    private function resolveInvitationStatus(Generator $faker): InvitationStatus
    {
        $roll = $faker->numberBetween(1, 100);

        return match (true) {
            $roll <= 75 => InvitationStatus::Accepted,
            $roll <= 90 => InvitationStatus::Pending,
            default => InvitationStatus::Declined,
        };
    }
}
