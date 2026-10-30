<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

it('serves each supported locale and falls back to Spanish for anything else', function () {
    $course = Course::factory()->create([
        'slug' => 'bilingual-course',
        'status' => 'published',
        'title_es' => 'Título en español',
        'title_en' => 'English title',
    ]);

    $this->getJson('/api/courses/bilingual-course', ['Accept-Language' => 'en'])
        ->assertOk()->assertJsonPath('data.title', 'English title');

    $this->getJson('/api/courses/bilingual-course', ['Accept-Language' => 'es'])
        ->assertOk()->assertJsonPath('data.title', 'Título en español');

    // Unsupported tags fall back to the default locale, never 500.
    $this->getJson('/api/courses/bilingual-course', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertOk()->assertJsonPath('data.title', 'Título en español');

    // An English column not written yet reads the authored Spanish, not a blank.
    $course->update(['title_en' => null]);
    $this->getJson('/api/courses/bilingual-course', ['Accept-Language' => 'en'])
        ->assertOk()->assertJsonPath('data.title', 'Título en español');

    expect($course->fresh()->localizedTitle('en'))->toBe('Título en español');
});

it('reads the primary subtag of the first tag only, and ignores q-values', function () {
    Course::factory()->create([
        'slug' => 'negotiated-course',
        'status' => 'published',
        'title_es' => 'Título en español',
        'title_en' => 'English title',
    ]);

    $title = fn (array $headers) => $this->getJson('/api/courses/negotiated-course', $headers)
        ->assertOk()->json('data.title');

    expect($title(['Accept-Language' => 'en-GB']))->toBe('English title')     // region subtag → en
        ->and($title(['Accept-Language' => 'EN']))->toBe('English title')      // case-insensitive
        ->and($title(['Accept-Language' => 'es-ES,en;q=0.8']))->toBe('Título en español')
        ->and($title(['Accept-Language' => 'ca-ES,es;q=0.9']))->toBe('Título en español') // Catalan → default
        ->and($title(['Accept-Language' => '']))->toBe('Título en español');   // empty header

    // Two deliberate divergences from RFC 7231, pinned because neither is
    // written down anywhere else. First: q-values are not parsed, so a client
    // that ranks English highest is still served Spanish…
    expect($title(['Accept-Language' => 'es;q=0.1,en;q=0.9']))->toBe('Título en español')
        ->and($title(['Accept-Language' => 'fr;q=0.2,en;q=1.0']))->toBe('Título en español');

    // …second, only the first two characters are read, so a value that is not
    // a language tag at all still resolves — generously, never with a 500.
    expect($title(['Accept-Language' => 'english']))->toBe('English title')
        ->and($title(['Accept-Language' => '<script>alert(1)</script>']))->toBe('Título en español');
});

it('localizes server messages into every supported locale', function () {
    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-locale']);
    config(['services.bunny.token_key' => '']);

    $messages = [];
    foreach (config('locales.supported') as $locale) {
        $messages[$locale] = $this->withHeader('Accept-Language', $locale)
            ->getJson("/api/lessons/{$lesson->id}/playback")
            ->assertStatus(503)
            ->json('message');
    }

    // Two distinct languages, neither falling through to the other.
    expect($messages['es'])->toBe('La reproducción de vídeo no está disponible temporalmente. Inténtalo de nuevo más tarde.')
        ->and($messages['en'])->toBe('Video playback is temporarily unavailable. Please try again later.');
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
