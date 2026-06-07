<?php

use App\Models\User;
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
