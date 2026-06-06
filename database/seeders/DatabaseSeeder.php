<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CuisineTypeSeeder::class,
            AllergenSeeder::class,
            UserSeeder::class,
            RestaurantSeeder::class,
            MenuSeeder::class,
            ServiceScheduleSeeder::class,
            ScheduleExceptionSeeder::class,
            ReservationSeeder::class,
            PreOrderSeeder::class,
            PaymentSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            FriendGroupSeeder::class,
        ]);
    }
}
