<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

it('serves each supported locale and falls back to French for anything else', function () {
    $course = Course::factory()->create([
        'slug' => 'trilingual-course',
        'status' => 'published',
        'title_es' => 'Titre français',
        'title_en' => 'العنوان بالعربية',
    ]);

    // Arabic has its own authored column.
    $this->getJson('/api/courses/trilingual-course', ['Accept-Language' => 'ar'])
        ->assertOk()->assertJsonPath('data.title', 'العنوان بالعربية');

    // English has a fully translated interface but no content columns yet:
    // course material falls back to the authored French rather than blank.
    $this->getJson('/api/courses/trilingual-course', ['Accept-Language' => 'en'])
        ->assertOk()->assertJsonPath('data.title', 'Titre français');

    // Unsupported tags fall back to the default locale, never 500.
    $this->getJson('/api/courses/trilingual-course', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertOk()->assertJsonPath('data.title', 'Titre français');

    expect($course->fresh()->localizedTitle('en'))->toBe('Titre français');
});

it('reads the primary subtag of the first tag only, and ignores q-values', function () {
    Course::factory()->create([
        'slug' => 'negotiated-course',
        'status' => 'published',
        'title_es' => 'Titre français',
        'title_en' => 'العنوان بالعربية',
    ]);

    $title = fn (array $headers) => $this->getJson('/api/courses/negotiated-course', $headers)
        ->assertOk()->json('data.title');

    expect($title(['Accept-Language' => 'ar-DZ']))->toBe('العنوان بالعربية')   // region subtag → ar
        ->and($title(['Accept-Language' => 'AR']))->toBe('العنوان بالعربية')    // case-insensitive
        ->and($title(['Accept-Language' => 'fr,ar;q=0.8']))->toBe('Titre français')
        ->and($title(['Accept-Language' => '']))->toBe('Titre français')        // empty header
        ->and($title([]))->toBe('Titre français');                              // no header at all

    // Two deliberate divergences from RFC 7231, pinned because neither is
    // written down anywhere else. First: q-values are not parsed, so a client
    // that ranks Arabic highest is still served French…
    expect($title(['Accept-Language' => 'fr;q=0.1,ar;q=0.9']))->toBe('Titre français')
        ->and($title(['Accept-Language' => 'en;q=0.2,ar;q=1.0']))->toBe('Titre français');

    // …second, only the first two characters are read, so a value that is not
    // a language tag at all still resolves — generously, never with a 500.
    expect($title(['Accept-Language' => 'arabic']))->toBe('العنوان بالعربية')
        ->and($title(['Accept-Language' => '<script>alert(1)</script>']))->toBe('Titre français');
});

it('localizes server messages into every supported locale', function () {
    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-locale']);
    config(['services.bunny.token_key' => '']);

    $messages = [];
    foreach (['fr', 'ar', 'en'] as $locale) {
        $messages[$locale] = $this->withHeader('Accept-Language', $locale)
            ->getJson("/api/lessons/{$lesson->id}/playback")
            ->assertStatus(503)
            ->json('message');
    }

    // Three distinct languages, none of them falling through to another.
    expect($messages['fr'])->toContain('indisponible')
        ->and($messages['en'])->toBe('Video playback is temporarily unavailable. Please try again later.')
        ->and($messages['ar'])->not->toBe($messages['fr'])
        ->and($messages['ar'])->not->toBe($messages['en']);
});

it('accepts every supported locale as a user preference and rejects the rest', function () {
    $user = User::factory()->create();

    foreach (config('locales.supported') as $locale) {
        $this->actingAs($user)->patchJson('/api/me', ['locale' => $locale])
            ->assertOk()->assertJsonPath('data.locale', $locale);
    }

    $this->actingAs($user)->patchJson('/api/me', ['locale' => 'de'])
        ->assertUnprocessable()->assertJsonValidationErrors(['locale']);
});

it('throttles every API route, not only the ones with their own limiter', function () {
    // The 'api' limiter allows 120 requests a minute per IP.
    foreach (range(1, 120) as $ignored) {
        $this->getJson('/api/courses')->assertOk();
    }

    $this->getJson('/api/courses')->assertStatus(429);
});
