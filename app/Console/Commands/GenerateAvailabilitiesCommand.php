<?php

namespace App\Console\Commands;

use App\Enums\RestaurantStatus;
use App\Jobs\GenerateRestaurantAvailabilitiesJob;
use App\Models\Restaurant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('availabilities:generate {--all : Include unpublished restaurants}')]
#[Description('Dispatch rolling availability generation for restaurants.')]
class GenerateAvailabilitiesCommand extends Command
{
    public function handle(): int
    {
        $query = Restaurant::query()->select('id');

        if (! $this->option('all')) {
            $query->where('status', RestaurantStatus::Published->value);
        }

        $count = 0;

        $query->chunkById(100, function (Collection $restaurants) use (&$count): void {
            foreach ($restaurants as $restaurant) {
                GenerateRestaurantAvailabilitiesJob::dispatch($restaurant->id);
                $count++;
            }
        });

        $this->info("Dispatched availability generation for {$count} restaurant(s).");

        return self::SUCCESS;
    }
}
