<?php

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Roles are referenced by almost every feature (role: middleware, the
        // actingAs* helpers, factory role states), so seed them once globally.
        $this->seed(RoleSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a client, authenticate as them (web/session guard, as the SPA does),
 * and return the user. Pass attribute overrides as needed.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsClient(array $attributes = []): User
{
    $user = User::factory()->client()->create($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Create a restaurateur, authenticate as them, and return the user.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsRestaurateur(array $attributes = []): User
{
    $user = User::factory()->restaurateur()->create($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Create an admin, authenticate as them, and return the user.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsAdmin(array $attributes = []): User
{
    $user = User::factory()->admin()->create($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Build a confirmed pre-order reservation for $organizer (persisted as the
 * organizer participant) with a pending, empty order ready to receive items.
 *
 * @param  array<string, mixed>  $reservationAttributes
 * @return array{restaurant: Restaurant, reservation: Reservation, participant: ReservationParticipant, order: Order}
 */
function preorderContext(User $organizer, array $reservationAttributes = []): array
{
    $restaurant = Restaurant::factory()->published()->create(['accepts_preorders' => true]);

    // Créneau confortablement futur : l'annulation reste dans les délais quel que soit
    // le deadline du restaurant. reserved_at/slot_at/ends_at restent cohérents.
    $reservedAt = CarbonImmutable::now()->addDays(5)->setTime(20, 0);

    $reservation = Reservation::factory()->for($restaurant)->for($organizer, 'organizer')->create(array_merge([
        'is_preorder' => true,
        'status' => ReservationStatus::Confirmed,
        'reserved_at' => $reservedAt,
        'slot_at' => $reservedAt,
        'ends_at' => $reservedAt->addMinutes(90),
        'seating_duration_minutes' => 90,
    ], $reservationAttributes));

    $participant = ReservationParticipant::factory()->for($reservation)->for($organizer)->organizer()->create();

    $order = Order::factory()->for($reservation)->for($restaurant)->create([
        'status' => OrderStatus::Pending,
        'placed_at' => null,
        'stock_restored_at' => null,
        'items_total' => 0,
    ]);

    return compact('restaurant', 'reservation', 'participant', 'order');
}

/**
 * Create a menu item belonging to $restaurant (with its own category).
 *
 * @param  array<string, mixed>  $attributes
 */
function menuItemFor(Restaurant $restaurant, array $attributes = []): MenuItem
{
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);

    // Default to untracked stock so stock behaviour is opt-in and deterministic.
    return MenuItem::factory()->for($category, 'category')->create(array_merge(
        ['stock_quantity' => null],
        $attributes,
    ));
}
