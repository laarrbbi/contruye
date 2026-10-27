<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('lets an enrolled user download an attachment with its original name', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Fiche-revision.pdf', 'path' => 'lessons/attachments/abc123.pdf'],
        ],
    ]);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    Storage::disk('local')->put('lessons/attachments/abc123.pdf', '%PDF-1.4 fake pdf content');

    $response = $this->actingAs($user)
        ->get("/api/lessons/{$lesson->id}/attachments/0")
        ->assertOk()
        ->assertDownload('Fiche-revision.pdf');

    expect($response->streamedContent())->toBe('%PDF-1.4 fake pdf content');
});

it('downloads the attachment matching the requested index', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Premier.pdf', 'path' => 'lessons/attachments/first.pdf'],
            ['name' => 'Deuxieme.pdf', 'path' => 'lessons/attachments/second.pdf'],
        ],
    ]);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    Storage::disk('local')->put('lessons/attachments/first.pdf', 'first content');
    Storage::disk('local')->put('lessons/attachments/second.pdf', 'second content');

    $response = $this->actingAs($user)
        ->get("/api/lessons/{$lesson->id}/attachments/1")
        ->assertOk()
        ->assertDownload('Deuxieme.pdf');

    expect($response->streamedContent())->toBe('second content');
});

it('returns 404 for an out-of-range attachment index', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Seul.pdf', 'path' => 'lessons/attachments/only.pdf'],
        ],
    ]);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/attachments/5")
        ->assertNotFound();
});

it('returns 404 for a lesson without attachments', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['attachments' => null]);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/attachments/0")
        ->assertNotFound();
});

it('returns 404 when the file is missing on disk', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Fantome.pdf', 'path' => 'lessons/attachments/does-not-exist.pdf'],
        ],
    ]);
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/attachments/0")
        ->assertNotFound();
});

it('returns 403 for a non-enrolled user', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Prive.pdf', 'path' => 'lessons/attachments/private.pdf'],
        ],
    ]);

    Storage::disk('local')->put('lessons/attachments/private.pdf', 'private');

    $this->actingAs($user)
        ->getJson("/api/lessons/{$lesson->id}/attachments/0")
        ->assertForbidden();
});

it('refuses the attachment of a free-preview lesson in a module the student has not bought', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $owned = Module::factory()->for($course)->create(['order' => 1]);
    $unowned = Module::factory()->for($course)->create(['order' => 2]);

    $preview = Lesson::factory()->for($unowned)->create([
        'order' => 1,
        'is_free_preview' => true,
        'attachments' => [
            ['name' => 'Modele-contrat-VSP.docx', 'path' => 'lessons/attachments/vsp.docx'],
        ],
    ]);

    Storage::disk('local')->put('lessons/attachments/vsp.docx', 'le modèle payant');

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    // Deliberately stricter than the other lesson endpoints: playback,
    // progress and the reflex sheet all pass allowFreePreview: true, this one
    // does not. A preview lets you WATCH the teaser; the downloadable
    // template is the thing the module is sold for.
    $response = $this->actingAs($student)
        ->getJson("/api/lessons/{$preview->id}/attachments/0")
        ->assertForbidden();

    expect($response->getContent())->not->toContain('le modèle payant');

    // The asymmetry is the point: the same lesson accepts a heartbeat, so
    // "aligning" AttachmentController on the others would leak a paid file.
    $this->actingAs($student)
        ->postJson("/api/lessons/{$preview->id}/progress", ['position_seconds' => 30])
        ->assertOk();

    // The player payload agrees: the count teases, the name never travels.
    $module = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    )->firstWhere('id', $unowned->id);
    $lesson = collect($module['lessons'])->firstWhere('id', $preview->id);

    expect($lesson['is_locked'])->toBeFalse()
        ->and($lesson['attachments'])->toBe([])
        ->and($lesson['attachments_count'])->toBe(1);
});

it('returns 401 for guests', function () {
    $lesson = Lesson::factory()->create([
        'attachments' => [
            ['name' => 'Prive.pdf', 'path' => 'lessons/attachments/private.pdf'],
        ],
    ]);

    $this->getJson("/api/lessons/{$lesson->id}/attachments/0")
        ->assertUnauthorized();
});
