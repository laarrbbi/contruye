<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * The admin form presents duration as optional and sends null when it is left
 * blank. That reached a rule with no `nullable`, so the whole request 422'd —
 * and because the error came back keyed on a field the form does not register,
 * nothing rendered: the dialog simply refused to close.
 */
it('accepts a lesson with no duration, the way the admin form sends it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create(['order' => 1]);

    $lessonId = $this->actingAs($admin)->postJson("/api/admin/modules/{$module->id}/lessons", [
        'title_es' => 'Leçon sans durée',
        'title_en' => 'درس بدون مدة',
        'duration_seconds' => null,
    ])->assertCreated()->json('data.id');

    expect(Lesson::find($lessonId)->duration_seconds)->toBe(0);
});

it('lets a zero-duration lesson be edited again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create(['order' => 1]);
    $lesson = Lesson::factory()->for($module)->create(['order' => 1, 'duration_seconds' => 0]);

    // The form renders 0 as an empty box and sends null straight back, so this
    // is the round-trip that used to be permanently blocked.
    $this->actingAs($admin)->putJson("/api/admin/lessons/{$lesson->id}", [
        'title_es' => 'Titre corrigé',
        'title_en' => $lesson->title_en,
        'duration_seconds' => null,
    ])->assertOk();

    expect($lesson->fresh()->title_es)->toBe('Titre corrigé')
        ->and($lesson->fresh()->duration_seconds)->toBe(0);
});

it('still rejects a duration that is not a number', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create(['order' => 1]);

    $this->actingAs($admin)->postJson("/api/admin/modules/{$module->id}/lessons", [
        'title_es' => 'Leçon', 'title_en' => 'درس',
        'duration_seconds' => 'bientôt',
    ])->assertUnprocessable()->assertJsonValidationErrors(['duration_seconds']);

    $this->actingAs($admin)->postJson("/api/admin/modules/{$module->id}/lessons", [
        'title_es' => 'Leçon', 'title_en' => 'درس',
        'duration_seconds' => -5,
    ])->assertUnprocessable()->assertJsonValidationErrors(['duration_seconds']);
});
