<?php

namespace App\Actions\Reservation;

use App\Actions\Order\RestoreOrderStockAction;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCancelled;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\Reservation\CancellationDeadlinePassedException;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function __construct(private readonly RestoreOrderStockAction $restoreOrderStock) {}

    /**
     * Annule une réservation confirmée dans les délais. La capacité se libère d'elle-même :
     * le calcul de disponibilité ne compte que les statuts « occupants » (confirmée/installée),
     * donc passer en « annulée » suffit — il n'y a aucun compteur à restaurer. Le passage
     * confirmée → annulée est un UPDATE conditionnel (WHERE status = confirmed) idempotent
     * sous concurrence. Toute pré-commande encore annulable est annulée et son stock restitué.
     */
    public function handle(Reservation $reservation, User $cancelledBy, ?string $reason = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw InvalidStatusTransitionException::between(
                $reservation->status->value,
                ReservationStatus::Cancelled->value,
            );
        }

        $reservation->loadMissing('restaurant');

        // Le propriétaire du restaurant peut annuler à tout moment ; seul l'organisateur
        // (client) est tenu par le délai d'annulation, calculé depuis l'heure d'arrivée.
        $isOwner = $reservation->restaurant->owner_id === $cancelledBy->id;

        if (! $isOwner) {
            $deadline = $reservation->reserved_at->copy()
                ->subHours($reservation->restaurant->cancellation_deadline_hours);

            if (now()->greaterThan($deadline)) {
                throw new CancellationDeadlinePassedException;
            }
        }

        DB::transaction(function () use ($reservation, $cancelledBy, $reason): void {
            $transitioned = Reservation::query()
                ->whereKey($reservation->id)
                ->where('status', ReservationStatus::Confirmed->value)
                ->update([
                    'status' => ReservationStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'cancelled_by_id' => $cancelledBy->id,
                    'cancellation_reason' => $reason,
                ]);

            // Un cancel concurrent a déjà sorti la ligne de « confirmed ».
            if ($transitioned === 0) {
                throw InvalidStatusTransitionException::between(
                    $reservation->status->value,
                    ReservationStatus::Cancelled->value,
                );
            }

            // Annule la pré-commande attachée et restitue son stock, mais uniquement tant
            // qu'elle est annulable : une commande servie est terminale (jamais re-stockée).
            $order = $reservation->order()->first();

            $voidable = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing];

            if ($order !== null && in_array($order->status, $voidable, true)) {
                $this->restoreOrderStock->handle($order);
                $order->update(['status' => OrderStatus::Cancelled->value]);
            }
        });

        $reservation->refresh();

        ReservationCancelled::dispatch($reservation, $cancelledBy);

        return $reservation->load(['participants.user', 'service']);
    }
}
