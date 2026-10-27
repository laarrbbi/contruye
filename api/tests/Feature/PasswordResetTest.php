<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Ask for a reset link and hand back the token that went out in the mail.
 */
function resetTokenFor(User $user): string
{
    test()->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    expect($token)->not->toBeNull();

    return $token;
}

it('sends a queued reset notification with an SPA link for an existing user', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'amina@example.dz']);

    $this->postJson('/api/forgot-password', ['email' => 'amina@example.dz'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        $url = $notification->resetUrl($user);

        expect($url)
            ->toStartWith(rtrim(config('app.frontend_url'), '/').'/reset-password?')
            ->toContain('token='.$notification->token)
            ->toContain('email='.urlencode($user->email));

        return true;
    });
});

it('returns the same 200 response for unknown emails and sends nothing', function () {
    Notification::fake();

    $user = User::factory()->create();

    $known = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
    $unknown = $this->postJson('/api/forgot-password', ['email' => 'ghost@example.dz'])->assertOk();

    expect($unknown->json('message'))->toBe($known->json('message'));

    // Only the known user's notification went out — nothing for the unknown email.
    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    Notification::assertCount(1);
});

it('resets the password end to end: old password stops working, new one logs in', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'old-password-1']);

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    expect($token)->not->toBeNull();

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertOk()->assertJsonStructure(['message']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'old-password-1',
    ])->assertUnprocessable();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'new-password-1',
    ])->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects an invalid reset token with a 422', function () {
    $user = User::factory()->create(['password' => 'old-password-1']);

    $this->postJson('/api/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'old-password-1',
    ])->assertOk();
});

it('rejects a weak password with a 422', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('consumes the token, so a leaked reset e-mail is not a permanent backdoor', function () {
    Notification::fake();
    Event::fake([PasswordReset::class]);

    $user = User::factory()->create(['password' => 'old-password-1']);
    $user->forceFill(['remember_token' => 'stale-remember-token'])->save();

    $token = resetTokenFor($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'first-password-1',
        'password_confirmation' => 'first-password-1',
    ])->assertOk();

    // "Remember me" cookies issued before the reset must stop working too:
    // rotating the token is what actually signs the old browsers out.
    expect(Hash::check('first-password-1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('stale-remember-token')
        ->and($user->fresh()->remember_token)->toHaveLength(60);

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($user));

    // The same token again — whoever else read that mailbox arrives here.
    // Single use lives in the framework's broker, which is exactly the kind
    // of guarantee a dependency bump can change without anyone noticing.
    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'second-password-1',
        'password_confirmation' => 'second-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Hash::check('first-password-1', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('second-password-1', $user->fresh()->password))->toBeFalse();
});

it('writes the reset e-mail in the account language, not the request language', function () {
    Notification::fake();

    $arabicSpeaker = User::factory()->create(['locale' => 'ar']);
    $frenchSpeaker = User::factory()->create(['locale' => 'fr']);

    // Both requests carry the same French header — a user who has forgotten
    // their password is logged out, and whatever browser they borrow says
    // nothing about the language they read. The STORED preference decides.
    $this->withHeader('Accept-Language', 'fr')
        ->postJson('/api/forgot-password', ['email' => $arabicSpeaker->email])->assertOk();
    $this->withHeader('Accept-Language', 'fr')
        ->postJson('/api/forgot-password', ['email' => $frenchSpeaker->email])->assertOk();

    Notification::assertSentTo($arabicSpeaker, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
        expect($notification->locale)->toBe('ar');

        return true;
    });

    Notification::assertSentTo($frenchSpeaker, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
        expect($notification->locale)->toBe('fr');

        return true;
    });
});
