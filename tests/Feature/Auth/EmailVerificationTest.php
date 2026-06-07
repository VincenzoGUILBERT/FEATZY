<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Build a valid signed verification URL for the given user.
 */
function signedVerificationUrl(User $user): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );
}

it('verifies the email from a valid signed link and redirects with a success status', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();

    $this->get(signedVerificationUrl($user))
        ->assertRedirect(config('app.frontend_url').'/email/verified?status=success');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

it('redirects with an already status when the user is already verified', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->get(signedVerificationUrl($user))
        ->assertRedirect(config('app.frontend_url').'/email/verified?status=already');

    Event::assertNotDispatched(Verified::class);
});

it('redirects with an invalid status when the hash does not match', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('someone-else@example.com')],
    );

    $this->get($url)
        ->assertRedirect(config('app.frontend_url').'/email/verified?status=invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects with an invalid status when the signature is tampered with', function () {
    $user = User::factory()->unverified()->create();

    $url = signedVerificationUrl($user).'tampered';

    $this->get($url)
        ->assertRedirect(config('app.frontend_url').'/email/verified?status=invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects with an invalid status when the user no longer exists', function () {
    $user = User::factory()->unverified()->create();

    $url = signedVerificationUrl($user);

    $user->forceDelete();

    $this->get($url)
        ->assertRedirect(config('app.frontend_url').'/email/verified?status=invalid');
});

it('resends a verification link to an unverified user and returns a generic 200', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/email/verification-notification', [
        'email' => 'jane@example.com',
    ])->assertOk()->assertJsonStructure(['message']);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend a verification link to an already verified user but still returns a generic 200', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => now()]);

    $this->postJson('/api/email/verification-notification', [
        'email' => 'jane@example.com',
    ])->assertOk()->assertJsonStructure(['message']);

    Notification::assertNotSentTo($user, VerifyEmail::class);
});

it('returns a generic 200 and sends nothing for an unknown email (enumeration safe)', function () {
    Notification::fake();

    $this->postJson('/api/email/verification-notification', [
        'email' => 'nobody@example.com',
    ])->assertOk()->assertJsonStructure(['message']);

    Notification::assertNothingSent();
});

it('validates the email field on resend', function () {
    $this->postJson('/api/email/verification-notification', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rate limits the resend endpoint after six requests per minute', function () {
    Notification::fake();

    foreach (range(1, 6) as $ignored) {
        $this->postJson('/api/email/verification-notification', [
            'email' => 'jane@example.com',
        ])->assertOk();
    }

    $this->postJson('/api/email/verification-notification', [
        'email' => 'jane@example.com',
    ])->assertStatus(429);
});
