<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('requires authentication', function () {
    $this->postJson('/api/me/avatar', [
        'file' => UploadedFile::fake()->image('a.jpg'),
    ])->assertUnauthorized();
});

it('uploads an avatar and exposes its url', function () {
    $user = actingAsClient();

    $this->postJson('/api/me/avatar', [
        'file' => UploadedFile::fake()->image('avatar.jpg'),
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.avatar_url', fn ($url) => filled($url));

    expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

it('rejects a non-image file', function () {
    actingAsClient();

    $this->postJson('/api/me/avatar', [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('replaces the previous avatar (single file collection)', function () {
    $user = actingAsClient();

    $this->postJson('/api/me/avatar', ['file' => UploadedFile::fake()->image('a.jpg')])->assertOk();
    $this->postJson('/api/me/avatar', ['file' => UploadedFile::fake()->image('b.jpg')])->assertOk();

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

it('removes the avatar', function () {
    $user = actingAsClient();
    $user->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('avatar');

    $this->deleteJson('/api/me/avatar')->assertNoContent();

    expect($user->fresh()->getFirstMedia('avatar'))->toBeNull();
});
