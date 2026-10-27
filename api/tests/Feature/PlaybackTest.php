<?php

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

beforeEach(function () {
    config([
        'services.bunny.library_id' => '123456',
        'services.bunny.token_key' => 'test-token-key',
        'services.bunny.embed_ttl' => 14400,
    ]);
});

it('lets a guest play a free preview lesson with a signed embed url', function () {
    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-free-123']);

    $response = $this->getJson("/api/lessons/{$lesson->id}/playback")->assertOk();

    $data = $response->json('data');

    expect($data['url'])->toContain('/embed/123456/vid-free-123')
        ->and($data['watermark'])->toBeNull()
        ->and($data['expires_at'])->not->toBeNull();

    parse_str((string) parse_url($data['url'], PHP_URL_QUERY), $query);

    expect($query['token'])->toMatch('/^[0-9a-f]{64}$/')
        ->and(ctype_digit($query['expires']))->toBeTrue()
        ->and((int) $query['expires'])->toBeGreaterThan(now()->timestamp);
});

it('signs the token as sha256(key . videoId . expires)', function () {
    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-sign-me']);

    $url = $this->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertOk()
        ->json('data.url');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['token'])
        ->toBe(hash('sha256', 'test-token-key'.'vid-sign-me'.$query['expires']));
});

it('returns 401 for a guest on a paid lesson', function () {
    $lesson = Lesson::factory()->create(['bunny_video_id' => 'vid-paid-1']);

    $this->getJson("/api/lessons/{$lesson->id}/playback")->assertUnauthorized();
});

it('returns 403 for an authenticated user who is not enrolled', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['bunny_video_id' => 'vid-paid-2']);

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertForbidden();
});

it('lets an enrolled user play a paid lesson with their email as watermark', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['bunny_video_id' => 'vid-paid-3']);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertOk()
        ->assertJsonPath('data.watermark', $user->email);
});

it('lets an admin play a paid lesson without an enrollment', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create(['bunny_video_id' => 'vid-paid-4']);

    $this->actingAs($admin)
        ->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertOk()
        ->assertJsonPath('data.watermark', $admin->email);
});

it('returns 503 when the bunny token key is not configured', function () {
    config(['services.bunny.token_key' => '']);

    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-503-a']);

    // Pinned to French: the point here is the 503, and the message is
    // localized — the test client's own Accept-Language would pick English.
    $this->withHeader('Accept-Language', 'fr')
        ->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertStatus(503)
        ->assertJsonPath('message', 'La lecture vidéo est temporairement indisponible. Veuillez réessayer plus tard.');
});

it('returns 503 when the bunny library id is not configured', function () {
    config(['services.bunny.library_id' => null]);

    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => 'vid-503-b']);

    // Pinned to French: the point here is the 503, and the message is
    // localized — the test client's own Accept-Language would pick English.
    $this->withHeader('Accept-Language', 'fr')
        ->getJson("/api/lessons/{$lesson->id}/playback")
        ->assertStatus(503)
        ->assertJsonPath('message', 'La lecture vidéo est temporairement indisponible. Veuillez réessayer plus tard.');
});

it('returns 404 for a lesson without a bunny video id', function () {
    $lesson = Lesson::factory()->freePreview()->create(['bunny_video_id' => null]);

    $this->getJson("/api/lessons/{$lesson->id}/playback")->assertNotFound();
});
