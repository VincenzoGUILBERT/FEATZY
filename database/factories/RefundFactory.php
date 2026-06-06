<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => fake()->numberBetween(500, 20000),
            'reason' => fake()->optional()->sentence(),
            'stripe_refund_id' => fake()->optional()->regexify('re_[A-Za-z0-9]{24}'),
            'status' => RefundStatus::Pending,
            'processed_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RefundStatus::Succeeded,
            'processed_at' => now(),
        ]);
    }
}
