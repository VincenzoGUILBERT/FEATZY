<?php

use App\Enums\ReviewStatus;
use App\Models\Restaurant;
use App\Models\Review;

// ---------------------------------------------------------------------------
// index
// ---------------------------------------------------------------------------

test('an admin can list every review and filter by status', function (): void {
    actingAsAdmin();

    Review::factory()->count(2)->pending()->create();
    Review::factory()->published()->create();

    $this->getJson('/api/admin/reviews')->assertOk()->assertJsonCount(3, 'data');

    $this->getJson('/api/admin/reviews?filter[status]='.ReviewStatus::Pending->value)
        ->assertOk()->assertJsonCount(2, 'data');
});

test('non-admins cannot access the moderation board', function (): void {
    $this->getJson('/api/admin/reviews')->assertUnauthorized();

    actingAsClient();
    $this->getJson('/api/admin/reviews')->assertForbidden();

    actingAsRestaurateur();
    $this->getJson('/api/admin/reviews')->assertForbidden();
});

// ---------------------------------------------------------------------------
// publish
// ---------------------------------------------------------------------------

test('publishing a review makes it count towards the restaurant rating', function (): void {
    actingAsAdmin();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => null, 'reviews_count' => 0]);
    $review = Review::factory()->pending()->for($restaurant)->create(['rating' => 4]);

    $this->postJson("/api/admin/reviews/{$review->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', ReviewStatus::Published->value);

    $restaurant->refresh();

    expect((float) $restaurant->average_rating)->toBe(4.0)
        ->and($restaurant->reviews_count)->toBe(1);
});

// ---------------------------------------------------------------------------
// hide
// ---------------------------------------------------------------------------

test('hiding a published review removes it from the aggregate', function (): void {
    actingAsAdmin();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => null, 'reviews_count' => 0]);

    $hidden = Review::factory()->published()->for($restaurant)->create(['rating' => 4]);
    Review::factory()->published()->for($restaurant)->create(['rating' => 2]);

    $this->postJson("/api/admin/reviews/{$hidden->id}/hide")
        ->assertOk()
        ->assertJsonPath('data.status', ReviewStatus::Hidden->value);

    $restaurant->refresh();

    expect((float) $restaurant->average_rating)->toBe(2.0)
        ->and($restaurant->reviews_count)->toBe(1);
});

test('hiding a pending review leaves the aggregate untouched', function (): void {
    actingAsAdmin();
    $restaurant = Restaurant::factory()->published()->create(['average_rating' => 3.5, 'reviews_count' => 2]);
    $review = Review::factory()->pending()->for($restaurant)->create();

    $this->postJson("/api/admin/reviews/{$review->id}/hide")->assertOk();

    $restaurant->refresh();

    expect((float) $restaurant->average_rating)->toBe(3.5)
        ->and($restaurant->reviews_count)->toBe(2);
});

test('non-admins cannot moderate reviews', function (): void {
    $review = Review::factory()->pending()->create();

    $this->postJson("/api/admin/reviews/{$review->id}/publish")->assertUnauthorized();

    actingAsClient();
    $this->postJson("/api/admin/reviews/{$review->id}/publish")->assertForbidden();
    $this->postJson("/api/admin/reviews/{$review->id}/hide")->assertForbidden();

    actingAsRestaurateur();
    $this->postJson("/api/admin/reviews/{$review->id}/publish")->assertForbidden();
    $this->postJson("/api/admin/reviews/{$review->id}/hide")->assertForbidden();
});
