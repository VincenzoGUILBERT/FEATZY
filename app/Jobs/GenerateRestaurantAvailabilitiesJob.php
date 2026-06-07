<?php

namespace App\Jobs;

use App\Actions\Availability\GenerateServiceAvailabilitiesAction;
use App\Data\Availability\GenerateAvailabilitiesData;
use App\Models\Restaurant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRestaurantAvailabilitiesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $restaurantId) {}

    /**
     * Regenerate the restaurant's rolling availability window (today → horizon).
     */
    public function handle(GenerateServiceAvailabilitiesAction $action): void
    {
        $restaurant = Restaurant::query()->find($this->restaurantId);

        if ($restaurant === null) {
            return;
        }

        $action->handle($restaurant, new GenerateAvailabilitiesData);
    }
}
