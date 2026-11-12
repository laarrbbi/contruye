<?php

use App\Mail\NewsletterConfirmMail;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * The newsletter: double opt-in, the same answer whoever signs up, one-click
 * unsubscription, and the owner's list with each address's unsubscribe link.
 */

it('signs up with double opt-in: pending until the link is clicked', function () {
    Mail::fake();

    $this->postJson('/api/newsletter', ['email' => ' Lucia@Example.es ', 'locale' => 'fr', 'source' => 'calculator'])
        ->assertStatus(202);

    $subscriber = NewsletterSubscriber::query()->sole();
    expect($subscriber->email)->toBe('lucia@example.es')
        ->and($subscriber->status)->toBe('pending')
        ->and($subscriber->locale)->toBe('fr')
        ->and($subscriber->source)->toBe('calculator')
        ->and($subscriber->consent_ip)->not->toBeNull();

    Mail::assertQueued(NewsletterConfirmMail::class, fn (NewsletterConfirmMail $mail) => $mail->hasTo('lucia@example.es')
        && $mail->locale === 'fr'
        && str_contains($mail->render(), 'newsletter?action=confirm&amp;token='.$subscriber->token));

    $this->postJson('/api/newsletter/confirm', ['token' => $subscriber->token])
        ->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect($subscriber->fresh()->status)->toBe('confirmed')
        ->and($subscriber->fresh()->confirmed_at)->not->toBeNull();
});

it('answers the same whether the address is new or already confirmed, and never floods an inbox', function () {
    Mail::fake();
    NewsletterSubscriber::query()->create(['email' => 'javier@example.es', 'status' => 'confirmed', 'token' => NewsletterSubscriber::newToken(), 'confirmed_at' => now()]);

    $known = $this->postJson('/api/newsletter', ['email' => 'javier@example.es'])->assertStatus(202)->json();
    $new = $this->postJson('/api/newsletter', ['email' => 'nueva@example.es'])->assertStatus(202)->json();
    expect($known)->toBe($new);

    // A confirmed address gets no new e-mail; a pending one gets one every ten minutes at most.
    $this->postJson('/api/newsletter', ['email' => 'nueva@example.es'])->assertStatus(202);
    Mail::assertQueuedCount(1);

    $this->travel(11)->minutes();
    $this->postJson('/api/newsletter', ['email' => 'nueva@example.es'])->assertStatus(202);
    Mail::assertQueuedCount(2);
    expect(NewsletterSubscriber::query()->where('email', 'nueva@example.es')->count())->toBe(1);
});

it('ignores the hidden field bots fill', function () {
    Mail::fake();

    $this->postJson('/api/newsletter', ['email' => 'bot@example.es', 'website' => 'http://spam.example'])->assertStatus(202);

    expect(NewsletterSubscriber::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('unsubscribes in one click, and a new sign-up starts over with a new link', function () {
    Mail::fake();
    $subscriber = NewsletterSubscriber::query()->create(['email' => 'ana@example.es', 'status' => 'confirmed', 'token' => NewsletterSubscriber::newToken(), 'confirmed_at' => now()]);
    $old = $subscriber->token;

    $this->postJson('/api/newsletter/unsubscribe', ['token' => $old])->assertOk()->assertJsonPath('data.status', 'unsubscribed');
    expect($subscriber->fresh()->status)->toBe('unsubscribed');

    $this->postJson('/api/newsletter', ['email' => 'ana@example.es'])->assertStatus(202);
    $fresh = $subscriber->fresh();
    expect($fresh->status)->toBe('pending')->and($fresh->token)->not->toBe($old);
    Mail::assertQueued(NewsletterConfirmMail::class);

    // The old link no longer does anything.
    $this->postJson('/api/newsletter/confirm', ['token' => $old])->assertNotFound();
});

it('refuses an address that is not one', function () {
    $this->postJson('/api/newsletter', ['email' => 'no-es-un-correo'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/newsletter', ['email' => 'a@example.es', 'source' => 'otra'])->assertUnprocessable()->assertJsonValidationErrors('source');
});

it('gives the owner the list and the confirmed addresses with their unsubscribe links', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    NewsletterSubscriber::query()->create(['email' => 'confirmada@example.es', 'status' => 'confirmed', 'token' => NewsletterSubscriber::newToken(), 'confirmed_at' => now(), 'source' => 'home']);
    NewsletterSubscriber::query()->create(['email' => '=HYPERLINK("x")@example.es', 'status' => 'confirmed', 'token' => NewsletterSubscriber::newToken(), 'confirmed_at' => now()]);
    NewsletterSubscriber::query()->create(['email' => 'pendiente@example.es', 'status' => 'pending', 'token' => NewsletterSubscriber::newToken()]);

    $this->actingAs($admin)->getJson('/api/admin/newsletter')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.counts.confirmed', 2)
        ->assertJsonPath('meta.counts.pending', 1)
        ->assertJsonMissingPath('data.0.token');

    $csv = $this->actingAs($admin)->get('/api/admin/newsletter/export')->assertOk()->streamedContent();
    expect($csv)->toContain('confirmada@example.es')
        ->toContain('newsletter?action=unsubscribe&token=')
        ->toContain("'=HYPERLINK")
        ->not->toContain('pendiente@example.es');

    $student = User::factory()->create();
    $this->actingAs($student)->getJson('/api/admin/newsletter')->assertForbidden();
});
