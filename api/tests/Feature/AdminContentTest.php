<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

it('creates a module with an auto-incremented order', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    Module::factory()->for($course)->create(['order' => 3]);

    $this->actingAs($admin)
        ->postJson("/api/admin/courses/{$course->id}/modules", [
            'title_fr' => 'Module suivant',
            'title_ar' => 'الوحدة التالية',
        ])
        ->assertCreated()
        ->assertJsonPath('data.order', 4)
        ->assertJsonPath('data.title_fr', 'Module suivant');

    $this->assertDatabaseHas('modules', [
        'course_id' => $course->id,
        'title_fr' => 'Module suivant',
        'order' => 4,
    ]);
});

it('updates and deletes a module', function () {
    $admin = User::factory()->admin()->create();
    $module = Module::factory()->create(['order' => 1]);

    $this->actingAs($admin)
        ->putJson("/api/admin/modules/{$module->id}", [
            'title_fr' => 'Module renommé',
            'title_ar' => 'وحدة معاد تسميتها',
        ])
        ->assertOk()
        ->assertJsonPath('data.title_fr', 'Module renommé');

    $this->actingAs($admin)
        ->deleteJson("/api/admin/modules/{$module->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('modules', ['id' => $module->id]);
});

it('creates a lesson with auto order, defaults and attachments', function () {
    $admin = User::factory()->admin()->create();
    $module = Module::factory()->create(['order' => 1]);
    Lesson::factory()->for($module)->create(['order' => 2]);

    $this->actingAs($admin)
        ->postJson("/api/admin/modules/{$module->id}/lessons", [
            'title_fr' => 'Nouvelle leçon',
            'title_ar' => 'درس جديد',
            'attachments' => [
                ['name' => 'Support PDF', 'path' => 'attachments/support.pdf'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.order', 3)
        ->assertJsonPath('data.duration_seconds', 0)
        ->assertJsonPath('data.is_free_preview', false)
        ->assertJsonPath('data.attachments.0.name', 'Support PDF');

    $this->assertDatabaseHas('lessons', [
        'module_id' => $module->id,
        'title_fr' => 'Nouvelle leçon',
        'order' => 3,
    ]);
});

it('rejects a lesson attachment without a path', function () {
    $admin = User::factory()->admin()->create();
    $module = Module::factory()->create(['order' => 1]);

    $this->actingAs($admin)
        ->postJson("/api/admin/modules/{$module->id}/lessons", [
            'title_fr' => 'Leçon',
            'title_ar' => 'درس',
            'attachments' => [['name' => 'Sans chemin']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attachments.0.path']);
});

it('updates and deletes a lesson', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create(['order' => 1]);

    $this->actingAs($admin)
        ->putJson("/api/admin/lessons/{$lesson->id}", [
            'title_fr' => 'Leçon mise à jour',
            'title_ar' => 'درس محدث',
            'bunny_video_id' => 'bunny-999',
            'duration_seconds' => 1234,
            'is_free_preview' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title_fr', 'Leçon mise à jour')
        ->assertJsonPath('data.bunny_video_id', 'bunny-999')
        ->assertJsonPath('data.duration_seconds', 1234)
        ->assertJsonPath('data.is_free_preview', true);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/lessons/{$lesson->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
});

it('reorders modules by renumbering from the given sequence', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $first = Module::factory()->for($course)->create(['order' => 1]);
    $second = Module::factory()->for($course)->create(['order' => 2]);
    $third = Module::factory()->for($course)->create(['order' => 3]);

    $this->actingAs($admin)
        ->putJson("/api/admin/courses/{$course->id}/modules/reorder", [
            'module_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.id', $third->id);

    expect($third->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2)
        ->and($second->fresh()->order)->toBe(3);
});

it('rejects a module reorder containing a foreign module id', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $mine = Module::factory()->for($course)->create(['order' => 1]);
    Module::factory()->for($course)->create(['order' => 2]);
    $foreign = Module::factory()->create(['order' => 1]); // other course

    $this->actingAs($admin)
        ->putJson("/api/admin/courses/{$course->id}/modules/reorder", [
            'module_ids' => [$mine->id, $foreign->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['module_ids.1']);
});

it('rejects a module reorder that omits some of the course modules', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $first = Module::factory()->for($course)->create(['order' => 1]);
    Module::factory()->for($course)->create(['order' => 2]);

    $this->actingAs($admin)
        ->putJson("/api/admin/courses/{$course->id}/modules/reorder", [
            'module_ids' => [$first->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['module_ids']);
});

it('reorders lessons within a module', function () {
    $admin = User::factory()->admin()->create();
    $module = Module::factory()->create(['order' => 1]);
    $first = Lesson::factory()->for($module)->create(['order' => 1]);
    $second = Lesson::factory()->for($module)->create(['order' => 2]);

    $this->actingAs($admin)
        ->putJson("/api/admin/modules/{$module->id}/lessons/reorder", [
            'lesson_ids' => [$second->id, $first->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.id', $second->id);

    expect($second->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2);
});

it('rejects a lesson reorder containing a foreign lesson id', function () {
    $admin = User::factory()->admin()->create();
    $module = Module::factory()->create(['order' => 1]);
    $mine = Lesson::factory()->for($module)->create(['order' => 1]);
    $foreign = Lesson::factory()->create(['order' => 1]); // other module

    $this->actingAs($admin)
        ->putJson("/api/admin/modules/{$module->id}/lessons/reorder", [
            'lesson_ids' => [$mine->id, $foreign->id],
        ])
        ->assertUnprocessable();
});
