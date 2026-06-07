<?php

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\ServiceSchedule;
use App\Support\Availability\AvailabilityResolver;
use App\Support\Availability\ResolvedSlot;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// Boot the app (no DB queries are run) so Eloquent's date cast can resolve the
// connection's date format while we exercise the resolver against in-memory models.
uses(TestCase::class);

// Known reference week: 2023-01-01 was a Sunday, so 01->07 covers Sun..Sat.
const MONDAY = '2023-01-02';
const SUNDAY = '2023-01-01';

function makeRestaurant(array $schedules = [], array $exceptions = []): Restaurant
{
    $restaurant = new Restaurant;
    $restaurant->setRelation('serviceSchedules', collect($schedules));
    $restaurant->setRelation('scheduleExceptions', collect($exceptions));

    return $restaurant;
}

function makeSchedule(DayOfWeek $day, ServiceType $service, int $capacity = 40, int $maxPartySize = 8, bool $isActive = true): ServiceSchedule
{
    return new ServiceSchedule([
        'day_of_week' => $day->value,
        'service_type' => $service->value,
        'start_time' => '19:00:00',
        'end_time' => '22:30:00',
        'capacity' => $capacity,
        'max_party_size' => $maxPartySize,
        'is_active' => $isActive,
    ]);
}

function makeException(string $date, ?ServiceType $service, bool $isClosed = false, ?int $capacity = null, ?int $maxPartySize = null): ScheduleException
{
    return new ScheduleException([
        'date' => $date,
        'service_type' => $service?->value,
        'is_closed' => $isClosed,
        'capacity' => $capacity,
        'max_party_size' => $maxPartySize,
    ]);
}

function resolveSlot(Restaurant $restaurant, string $date, ServiceType $service): ?ResolvedSlot
{
    return (new AvailabilityResolver)->resolve($restaurant, CarbonImmutable::parse($date), $service);
}

it('resolves an active weekly schedule', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 40, 8)]);

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot)->not->toBeNull()
        ->and($slot->capacity)->toBe(40)
        ->and($slot->max_party_size)->toBe(8);
});

it('returns null when the weekly schedule is inactive', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, isActive: false)]);

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull();
});

it('returns null for a different weekday', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Monday, ServiceType::Dinner)]);

    expect(resolveSlot($restaurant, SUNDAY, ServiceType::Dinner))->toBeNull();
});

it('returns null for a service without a schedule', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Monday, ServiceType::Dinner)]);

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Lunch))->toBeNull();
});

it('returns null when nothing is configured', function () {
    expect(resolveSlot(makeRestaurant(), MONDAY, ServiceType::Dinner))->toBeNull();
});

it('treats a closed same-service exception as closed even over an open schedule', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner)],
        [makeException(MONDAY, ServiceType::Dinner, isClosed: true)],
    );

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull();
});

it('lets a same-service exception override capacity and party size', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 40, 8)],
        [makeException(MONDAY, ServiceType::Dinner, capacity: 120, maxPartySize: 20)],
    );

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot->capacity)->toBe(120)->and($slot->max_party_size)->toBe(20);
});

it('falls back to the weekly schedule for a null exception field', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 40, 8)],
        [makeException(MONDAY, ServiceType::Dinner, capacity: null, maxPartySize: null)],
    );

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot->capacity)->toBe(40)->and($slot->max_party_size)->toBe(8);
});

it('opens a special service via a same-service exception without a weekly schedule', function () {
    $restaurant = makeRestaurant(
        [],
        [makeException(MONDAY, ServiceType::Dinner, capacity: 50, maxPartySize: 6)],
    );

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot->capacity)->toBe(50)->and($slot->max_party_size)->toBe(6);
});

it('returns null for a non-closed exception with no capacity and no schedule', function () {
    $restaurant = makeRestaurant(
        [],
        [makeException(MONDAY, ServiceType::Dinner, capacity: null)],
    );

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull();
});

it('closes the whole day with a null-service closed exception', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner), makeSchedule(DayOfWeek::Monday, ServiceType::Lunch)],
        [makeException(MONDAY, null, isClosed: true)],
    );

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull()
        ->and(resolveSlot($restaurant, MONDAY, ServiceType::Lunch))->toBeNull();
});

it('lets a whole-day exception override the capacity of existing services', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 40, 8)],
        [makeException(MONDAY, null, capacity: 25)],
    );

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot->capacity)->toBe(25)->and($slot->max_party_size)->toBe(8);
});

it('does not let a whole-day exception invent a service without a schedule', function () {
    $restaurant = makeRestaurant(
        [],
        [makeException(MONDAY, null, capacity: 25)],
    );

    expect(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull();
});

it('prefers a same-service exception over a whole-day exception', function () {
    $restaurant = makeRestaurant(
        [makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 40, 8)],
        [
            makeException(MONDAY, null, isClosed: true),
            makeException(MONDAY, ServiceType::Dinner, capacity: 100, maxPartySize: 10),
        ],
    );

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    // Dinner stays open via its own exception; lunch (only whole-day-closed) is shut.
    expect($slot->capacity)->toBe(100)->and($slot->max_party_size)->toBe(10);
});

it('clamps max_party_size down to capacity', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Monday, ServiceType::Dinner, 10, 12)]);

    $slot = resolveSlot($restaurant, MONDAY, ServiceType::Dinner);

    expect($slot->capacity)->toBe(10)->and($slot->max_party_size)->toBe(10);
});

it('maps DayOfWeek to Carbon::dayOfWeek (0 = Sunday .. 6 = Saturday)', function (string $date, DayOfWeek $expected) {
    expect(DayOfWeek::from(CarbonImmutable::parse($date)->dayOfWeek))->toBe($expected);
})->with([
    ['2023-01-01', DayOfWeek::Sunday],
    ['2023-01-02', DayOfWeek::Monday],
    ['2023-01-03', DayOfWeek::Tuesday],
    ['2023-01-04', DayOfWeek::Wednesday],
    ['2023-01-05', DayOfWeek::Thursday],
    ['2023-01-06', DayOfWeek::Friday],
    ['2023-01-07', DayOfWeek::Saturday],
]);

it('resolves a Sunday schedule on a Sunday but not on a Monday', function () {
    $restaurant = makeRestaurant([makeSchedule(DayOfWeek::Sunday, ServiceType::Dinner)]);

    expect(resolveSlot($restaurant, SUNDAY, ServiceType::Dinner))->not->toBeNull()
        ->and(resolveSlot($restaurant, MONDAY, ServiceType::Dinner))->toBeNull();
});
