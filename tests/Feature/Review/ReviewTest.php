<?php

use App\Enums\ReservationStatus;
use App\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;

/**
 * Persist a completed reservation at $restaurant with $user as its organizer
 * participant — the precondition for leaving a review.
 */
function completedReservationFor(User $user, Restaurant $restaurant): Reservation
{
    $reservation = Reservation::factory()
        ->for($restaurant)
        ->for($user, 'organizer')
        ->create(['status' => ReservationStatus::Completed]);

    ReservationParticipant::factory()
        ->for($reservation)
        ->for($user)
        ->organizer()
        ->create();

    return $reservation;
}

// ---------------------------------------------------------------------------
// index — public, published-only
// ---------------------------------------------------------------------------

test('the public review list returns only published reviews of a published restaurant', function (): void {
    $restaurant = Restaurant::factory()->published()->create();

    Review::factory()->count(2)->published()->for($restaurant)->create();
    Review::factory()->pending()->for($restaurant)->create();
    Review::factory()->for($restaurant)->create(['status' => ReviewStatus::Hidden]);

    $response = $this->getJson("/api/discovery/restaurants/{$restaurant->id}/reviews");

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('the public review list 404s for a draft restaurant', function (): void {
    $restaurant = Restaurant::factory()->create(); // draft by default

    Review::factory()->published()->for($restaurant)->create();

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}/reviews")
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// store
// ---------------------------------------------------------------------------

test('a client can review a completed reservation they attended', function (): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $reservation = completedReservationFor($client, $restaurant);

    $response = $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 5,
        'comment' => 'Excellent.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', ReviewStatus::Pending->value)
        ->assertJsonPath('data.rating', 5);

    $this->assertDatabaseHas('reviews', [
        'restaurant_id' => $restaurant->id,
        'user_id' => $client->id,
        'reservation_id' => $reservation->id,
        'status' => ReviewStatus::Pending->value,
    ]);
});

test('a pending review leaves the restaurant aggregate untouched', function (): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => null, 'reviews_count' => 0]);
    $reservation = completedReservationFor($client, $restaurant);

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 5,
    ])->assertCreated();

    $restaurant->refresh();

    expect($restaurant->average_rating)->toBeNull()
        ->and($restaurant->reviews_count)->toBe(0);
});

test('the rating must be between 1 and 5', function (int $rating): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $reservation = completedReservationFor($client, $restaurant);

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => $rating,
    ])->assertUnprocessable()->assertJsonValidationErrorFor('rating');
})->with([0, 6]);

test('a reservation that is not completed cannot be reviewed', function (): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $reservation = Reservation::factory()->for($restaurant)->for($client, 'organizer')
        ->create(['status' => ReservationStatus::Confirmed]);
    ReservationParticipant::factory()->for($reservation)->for($client)->organizer()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 4,
    ])->assertUnprocessable()->assertJsonValidationErrorFor('reservation_id');
});

test('a reservation from another restaurant cannot be reviewed here', function (): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $other = Restaurant::factory()->published()->create();
    $reservation = completedReservationFor($client, $other);

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 4,
    ])->assertUnprocessable()->assertJsonValidationErrorFor('reservation_id');
});

test('a user who did not attend the reservation cannot review it', function (): void {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    // A completed reservation organized by someone else, with no participation.
    $reservation = completedReservationFor(User::factory()->create(), $restaurant);

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 4,
    ])->assertUnprocessable()->assertJsonValidationErrorFor('reservation_id');
});

test('the same reservation cannot be reviewed twice by the same user', function (): void {
    $client = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $reservation = completedReservationFor($client, $restaurant);

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 4,
    ])->assertCreated();

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => $reservation->id,
        'rating' => 3,
    ])->assertUnprocessable()->assertJsonValidationErrorFor('reservation_id');
});

test('guests cannot post reviews (role:client only)', function (): void {
    $restaurant = Restaurant::factory()->published()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => 1,
        'rating' => 4,
    ])->assertUnauthorized();

    actingAsRestaurateur();

    $this->postJson("/api/restaurants/{$restaurant->id}/reviews", [
        'reservation_id' => 1,
        'rating' => 4,
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

test('the author can edit their review', function (): void {
    $client = actingAsClient();
    $review = Review::factory()->pending()->for($client)->create(['rating' => 3]);

    $this->patchJson("/api/reviews/{$review->id}", ['rating' => 5, 'comment' => 'Updated.'])
        ->assertOk()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Updated.');
});

test('editing a published review re-syncs the restaurant aggregate', function (): void {
    $author = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => null, 'reviews_count' => 0]);

    $review = Review::factory()->published()->for($restaurant)->for($author)->create(['rating' => 4]);
    Review::factory()->published()->for($restaurant)->create(['rating' => 2]);

    $this->patchJson("/api/reviews/{$review->id}", ['rating' => 5])->assertOk();

    $restaurant->refresh();

    expect((float) $restaurant->average_rating)->toBe(3.5) // avg(5, 2)
        ->and($restaurant->reviews_count)->toBe(2);
});

test('editing or deleting a review requires authentication', function (): void {
    $review = Review::factory()->pending()->create();

    $this->patchJson("/api/reviews/{$review->id}", ['rating' => 1])->assertUnauthorized();
    $this->deleteJson("/api/reviews/{$review->id}")->assertUnauthorized();
});

test('a non-author cannot edit a review', function (): void {
    actingAsClient();
    $review = Review::factory()->pending()->create(['rating' => 3]);

    $this->patchJson("/api/reviews/{$review->id}", ['rating' => 1])->assertForbidden();
});

// ---------------------------------------------------------------------------
// destroy
// ---------------------------------------------------------------------------

test('the author can delete their review', function (): void {
    $client = actingAsClient();
    $review = Review::factory()->pending()->for($client)->create();

    $this->deleteJson("/api/reviews/{$review->id}")->assertNoContent();

    $this->assertSoftDeleted('reviews', ['id' => $review->id]);
});

test('deleting a published review re-syncs the restaurant aggregate', function (): void {
    $author = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => null, 'reviews_count' => 0]);

    $review = Review::factory()->published()->for($restaurant)->for($author)->create(['rating' => 4]);
    Review::factory()->published()->for($restaurant)->create(['rating' => 2]);

    $this->deleteJson("/api/reviews/{$review->id}")->assertNoContent();

    $restaurant->refresh();

    expect((float) $restaurant->average_rating)->toBe(2.0) // only the remaining one
        ->and($restaurant->reviews_count)->toBe(1);
});

test('a non-author cannot delete a review', function (): void {
    actingAsClient();
    $review = Review::factory()->pending()->create();

    $this->deleteJson("/api/reviews/{$review->id}")->assertForbidden();

    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'deleted_at' => null]);
});
