<?php

use App\Actions\Availability\GenerateServiceAvailabilitiesAction;
use App\Jobs\GenerateRestaurantAvailabilitiesJob;
use App\Models\Restaurant;
use App\Models\ServiceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

it('dispatches generation only for published restaurants by default', function () {
    Queue::fake();
    $published = Restaurant::factory()->published()->create();
    Restaurant::factory()->create();

    $this->artisan('availabilities:generate')->assertSuccessful();

    Queue::assertPushed(GenerateRestaurantAvailabilitiesJob::class, 1);
    Queue::assertPushed(
        GenerateRestaurantAvailabilitiesJob::class,
        fn (GenerateRestaurantAvailabilitiesJob $job): bool => $job->restaurantId === $published->id,
    );
});

it('includes unpublished restaurants with the --all option', function () {
    Queue::fake();
    Restaurant::factory()->published()->create();
    Restaurant::factory()->create();

    $this->artisan('availabilities:generate', ['--all' => true])->assertSuccessful();

    Queue::assertPushed(GenerateRestaurantAvailabilitiesJob::class, 2);
});

it('generates the rolling window when the job runs', function () {
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
    $restaurant = Restaurant::factory()->published()->create(['booking_horizon_days' => 7]);

    foreach (range(0, 6) as $dayOfWeek) {
        ServiceSchedule::factory()->for($restaurant)->create([
            'day_of_week' => $dayOfWeek, 'service_type' => 'lunch',
            'start_time' => '12:00:00', 'end_time' => '14:30:00',
            'capacity' => 30, 'max_party_size' => 6, 'is_active' => true,
        ]);
    }

    (new GenerateRestaurantAvailabilitiesJob($restaurant->id))->handle(app(GenerateServiceAvailabilitiesAction::class));

    // today .. today + 7 days inclusive = 8 lunch slots.
    $this->assertDatabaseCount('service_availabilities', 8);

    CarbonImmutable::setTestNow();
});

it('runs without error when the restaurant no longer exists', function () {
    (new GenerateRestaurantAvailabilitiesJob(999999))->handle(app(GenerateServiceAvailabilitiesAction::class));

    $this->assertDatabaseCount('service_availabilities', 0);
});
