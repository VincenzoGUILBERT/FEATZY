<?php

namespace App\Actions\Reservation;

use App\Data\Reservation\CreateReservationData;
use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCreated;
use App\Exceptions\Order\PreordersNotAcceptedException;
use App\Exceptions\Reservation\SlotUnavailableException;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Réserve un créneau pour l'organisateur, sans surbooking. La disponibilité est calculée
 * à la volée (couverts simultanés par chevauchement + pacing par créneau) : il n'y a pas de
 * compteur à décrémenter. La vérification finale et l'insertion se font donc sous un verrou
 * sérialisant par POOL de capacité, à l'intérieur d'une transaction. L'organisateur est
 * persisté comme premier participant dans la même transaction.
 */
class CreateReservationAction
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @throws PreordersNotAcceptedException
     * @throws SlotUnavailableException
     */
    public function handle(Restaurant $restaurant, CreateReservationData $data, User $organizer): Reservation
    {
        if ($data->is_preorder && ! $restaurant->accepts_preorders) {
            throw new PreordersNotAcceptedException;
        }

        $service = $restaurant->services()->active()->findOrFail($data->service_id);
        $service->setRelation('restaurant', $restaurant);

        $date = CarbonImmutable::parse($data->date);
        $slotStart = CarbonImmutable::parse($data->reserved_at);
        $partySize = $data->party_size;

        $min = $service->effectiveMinPartySize();
        $max = $service->effectiveMaxPartySize();

        if ($partySize < $min || $partySize > $max) {
            throw new SlotUnavailableException("Le nombre de couverts doit être compris entre {$min} et {$max}.");
        }

        // Pré-vérification hors verrou (échoue vite sur un créneau invalide / fermé / complet).
        if (! $this->availability->isSlotBookable($service, $date, $slotStart, $partySize)) {
            throw new SlotUnavailableException;
        }

        $lockKey = "avail:pool:{$restaurant->id}:{$service->capacity_pool_key}";

        try {
            $reservation = Cache::lock($lockKey, 10)->block(5, function () use ($restaurant, $service, $organizer, $data, $date, $slotStart, $partySize): Reservation {
                return DB::transaction(function () use ($restaurant, $service, $organizer, $data, $date, $slotStart, $partySize): Reservation {
                    // Re-vérification sous verrou, sur des données fraîches.
                    if (! $this->availability->isSlotBookable($service, $date, $slotStart, $partySize)) {
                        throw new SlotUnavailableException;
                    }

                    $duration = $service->effectiveSeatingDuration();
                    $end = $slotStart->addMinutes($duration);

                    // Anti double-booking : pas de réservation active du même organisateur
                    // chevauchant ce créneau.
                    $conflict = Reservation::query()
                        ->where('organizer_id', $organizer->id)
                        ->occupying()
                        ->overlapping($slotStart, $end)
                        ->exists();

                    if ($conflict) {
                        throw new SlotUnavailableException('Vous avez déjà une réservation sur ce créneau.');
                    }

                    $reservation = $restaurant->reservations()->create([
                        'service_id' => $service->id,
                        'organizer_id' => $organizer->id,
                        'party_size' => $partySize,
                        'reserved_at' => $slotStart,
                        'slot_at' => $slotStart,
                        'ends_at' => $end,
                        'seating_duration_minutes' => $duration,
                        'capacity_pool_key' => $service->capacity_pool_key,
                        'status' => ReservationStatus::Confirmed->value,
                        'is_preorder' => $data->is_preorder,
                        'special_requests' => $data->special_requests,
                    ]);

                    $reservation->participants()->create([
                        'user_id' => $organizer->id,
                        'role' => ParticipantRole::Organizer->value,
                        'invitation_status' => InvitationStatus::Accepted->value,
                        'responded_at' => now(),
                        'is_attending' => true,
                    ]);

                    return $reservation;
                });
            });
        } catch (LockTimeoutException) {
            throw new SlotUnavailableException('Trop de demandes simultanées sur ce service, merci de réessayer.');
        }

        ReservationCreated::dispatch($reservation);

        return $reservation->load(['participants.user', 'service', 'restaurant']);
    }
}
