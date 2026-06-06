<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'reservation_participant_id' => ReservationParticipant::factory(),
            'payer_user_id' => User::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => PaymentStatus::Pending,
            'stripe_payment_intent_id' => fake()->optional()->regexify('pi_[A-Za-z0-9]{24}'),
            'amount_refunded' => 0,
            'paid_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => fake()->sentence(),
        ]);
    }
}
