<?php

namespace Database\Factories;

use App\Models\MenuItemOption;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemOption>
 */
class OrderItemOptionFactory extends Factory
{
    protected $model = OrderItemOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'menu_item_option_id' => MenuItemOption::factory(),
            'label_snapshot' => fake()->words(2, true),
            'price_delta_snapshot' => fake()->numberBetween(-500, 1500),
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
