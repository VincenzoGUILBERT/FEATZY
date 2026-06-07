<?php

use App\Models\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    Storage::fake('public');
});

it('uploads a logo and keeps a single file when replaced', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/media/logo", [
        'file' => UploadedFile::fake()->image('logo.jpg'),
    ])->assertOk();

    expect($restaurant->fresh()->getMedia('logo'))->toHaveCount(1);

    $this->postJson("/api/restaurants/{$restaurant->id}/media/logo", [
        'file' => UploadedFile::fake()->image('logo2.jpg'),
    ])->assertOk();

    expect($restaurant->fresh()->getMedia('logo'))->toHaveCount(1);
});

it('appends gallery images', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/media/gallery", [
        'file' => UploadedFile::fake()->image('a.jpg'),
    ])->assertOk();
    $this->postJson("/api/restaurants/{$restaurant->id}/media/gallery", [
        'file' => UploadedFile::fake()->image('b.jpg'),
    ])->assertOk();

    expect($restaurant->fresh()->getMedia('gallery'))->toHaveCount(2);
});

it('rejects an unknown collection', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/media/banner", [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertNotFound();
});

it('rejects a non-image file', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/media/logo", [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('deletes an owned media item', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $restaurant->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('gallery');
    $media = $restaurant->getFirstMedia('gallery');

    $this->deleteJson("/api/restaurants/{$restaurant->id}/media/{$media->id}")
        ->assertNoContent();

    expect($restaurant->fresh()->getMedia('gallery'))->toHaveCount(0);
});

it('forbids uploading to a restaurant owned by someone else', function () {
    actingAsRestaurateur();
    $other = Restaurant::factory()->create();

    $this->postJson("/api/restaurants/{$other->id}/media/logo", [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertForbidden();
});
