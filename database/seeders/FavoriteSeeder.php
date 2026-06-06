<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Each client favorites 0 to 5 distinct random restaurants.
     */
    public function run(): void
    {
        $faker = fake('fr_FR');

        /** @var Collection<int, Restaurant> $restaurants */
        $restaurants = Restaurant::all(['id']);

        if ($restaurants->isEmpty()) {
            return;
        }

        $clients = User::role('client')->get(['id']);

        foreach ($clients as $client) {
            $count = min($faker->numberBetween(0, 5), $restaurants->count());

            if ($count === 0) {
                continue;
            }

            $picks = $restaurants->random($count);
            $picks = $picks instanceof Restaurant ? collect([$picks]) : $picks;

            foreach ($picks as $restaurant) {
                Favorite::firstOrCreate([
                    'user_id' => $client->id,
                    'restaurant_id' => $restaurant->id,
                ]);
            }
        }
    }
}
