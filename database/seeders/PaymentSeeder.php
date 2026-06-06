<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    /**
     * Seed split-bill payments for pre-ordered reservations.
     *
     * Modèle : chacun paie sa part. On ne crée des paiements que pour les
     * réservations pré-commandées (is_preorder = true) disposant d'un order.
     * Le montant dû par participant = somme des line_total de SES order_items.
     */
    public function run(): void
    {
        $faker = fake('fr_FR');

        $reservations = Reservation::query()
            ->where('is_preorder', true)
            ->whereHas('order.items')
            ->with(['order.items.participant'])
            ->get();

        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation, $faker): void {
                $this->seedReservationPayments($reservation, $faker);
            });
        }
    }

    /**
     * Create one payment per participant who has order items in this reservation.
     */
    private function seedReservationPayments(Reservation $reservation, Generator $faker): void
    {
        $order = $reservation->order;

        if ($order === null) {
            return;
        }

        $isCompleted = $reservation->status === ReservationStatus::Completed;

        /**
         * Regroupe les line_total par participant.
         *
         * @var array<int, array{participant: ReservationParticipant, amount: int}> $partShares
         */
        $partShares = [];

        foreach ($order->items as $item) {
            $participant = $item->participant;

            if ($participant === null) {
                continue;
            }

            // line_total est une colonne générée (stored) : la valeur lue ici est
            // fiable car le modèle a été rechargé depuis la base via le query above.
            $lineTotal = (int) $item->line_total;

            if (! isset($partShares[$participant->id])) {
                $partShares[$participant->id] = [
                    'participant' => $participant,
                    'amount' => 0,
                ];
            }

            $partShares[$participant->id]['amount'] += $lineTotal;
        }

        foreach ($partShares as $share) {
            $amount = $share['amount'];

            if ($amount <= 0) {
                continue;
            }

            $this->createPaymentForParticipant(
                $reservation,
                $share['participant'],
                $amount,
                $isCompleted,
                $faker,
            );
        }
    }

    /**
     * Create a single payment (and optional refund) for one participant.
     */
    private function createPaymentForParticipant(
        Reservation $reservation,
        ReservationParticipant $participant,
        int $amount,
        bool $isCompleted,
        Generator $faker,
    ): void {
        $isOnline = $faker->boolean(70);
        $method = $isOnline ? PaymentMethod::Online : PaymentMethod::Onsite;

        /** @var array<string, mixed> $attributes */
        $attributes = [
            'reservation_id' => $reservation->id,
            'reservation_participant_id' => $participant->id,
            'payer_user_id' => $participant->user_id,
            'amount' => $amount,
            'method' => $method,
            'amount_refunded' => 0,
        ];

        if ($isCompleted) {
            // Réservation honorée : le paiement est encaissé.
            $paidAt = $reservation->completed_at ?? Carbon::parse($reservation->reservation_date)->setTime(21, 0);

            $attributes['status'] = PaymentStatus::Paid;
            $attributes['paid_at'] = $paidAt;

            if ($isOnline) {
                $attributes['stripe_payment_intent_id'] = 'pi_'.Str::lower(Str::random(24));
            }
        } else {
            // Réservation confirmée / à venir : règlement encore en attente.
            $attributes['status'] = PaymentStatus::Pending;
        }

        $payment = Payment::create($attributes);

        // Remboursement éventuel : ~15 % des paiements en ligne encaissés
        // (réservations complétées) donnent lieu à un remboursement partiel ou total.
        if ($isCompleted && $isOnline && $faker->boolean(15)) {
            $this->createRefund($payment, $amount, $faker);
        }
    }

    /**
     * Create a succeeded refund (partial or full) and reconcile the payment.
     */
    private function createRefund(Payment $payment, int $amount, Generator $faker): void
    {
        $isFull = $faker->boolean(40);

        if ($isFull) {
            $refundAmount = $amount;
        } else {
            // Remboursement partiel : entre 20 % et 80 % du montant, borné à < amount.
            $refundAmount = (int) round($amount * $faker->numberBetween(20, 80) / 100);
            $refundAmount = max(1, min($refundAmount, $amount - 1));
        }

        /** @var list<string> $reasons */
        $reasons = [
            'Plat indisponible le jour du service',
            'Geste commercial suite à un retard en cuisine',
            'Annulation partielle de la commande',
            'Erreur de préparation',
            'Article retourné par le client',
        ];

        $paidAt = $payment->paid_at ?? Carbon::now();
        $processedAt = (clone $paidAt)->addDays($faker->numberBetween(1, 5));

        Refund::create([
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'reason' => $faker->randomElement($reasons),
            'stripe_refund_id' => 're_'.Str::lower(Str::random(24)),
            'status' => RefundStatus::Succeeded,
            'processed_at' => $processedAt,
        ]);

        $payment->update([
            'amount_refunded' => $refundAmount,
            'status' => $refundAmount >= $amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
        ]);
    }
}
