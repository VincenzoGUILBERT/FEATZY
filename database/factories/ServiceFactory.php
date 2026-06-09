<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([ServiceType::Lunch, ServiceType::Dinner]);
        $simultaneous = fake()->numberBetween(30, 120);

        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $type->label(),
            'type' => $type,
            'capacity_pool_key' => 'default',
            'max_simultaneous_covers' => $simultaneous,
            'max_covers_per_slot' => fake()->numberBetween(4, min($simultaneous, 20)),
            'seating_duration_minutes' => null,
            'slot_interval_minutes' => null,
            'min_party_size' => null,
            'max_party_size' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function lunch(): static
    {
        return $this->state(fn (): array => ['type' => ServiceType::Lunch, 'name' => ServiceType::Lunch->label()]);
    }

    public function dinner(): static
    {
        return $this->state(fn (): array => ['type' => ServiceType::Dinner, 'name' => ServiceType::Dinner->label(), 'position' => 1]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Fixe les deux plafonds de couverts (simultanés + pacing).
     */
    public function covers(int $simultaneous, int $perSlot): static
    {
        return $this->state(fn (): array => [
            'max_simultaneous_covers' => $simultaneous,
            'max_covers_per_slot' => $perSlot,
        ]);
    }
}
