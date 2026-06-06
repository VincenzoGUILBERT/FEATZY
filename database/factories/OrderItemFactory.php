<?php

namespace Database\Factories;

use App\Enums\OrderItemStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReservationParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reservation_participant_id' => ReservationParticipant::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name_snapshot' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price_snapshot' => fake()->numberBetween(500, 5000),
            'options_total_snapshot' => fake()->numberBetween(0, 1500),
            'status' => fake()->randomElement(OrderItemStatus::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
