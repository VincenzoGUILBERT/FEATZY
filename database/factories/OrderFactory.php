<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'restaurant_id' => Restaurant::factory(),
            'status' => fake()->randomElement(OrderStatus::cases()),
            'placed_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'items_total' => fake()->numberBetween(0, 50000),
            'stock_restored_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
