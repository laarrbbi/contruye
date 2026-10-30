<?php

use App\Models\ChecklistItem;
use App\Models\Course;
use App\Models\CustomTask;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;

function checklistCourse(): Course
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 90000]);

    Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_eur' => 12000])
        ->each(function (Module $module) {
            ChecklistItem::factory()->count(3)
                ->sequence(fn ($seq) => ['order' => $seq->index + 1, 'text_es' => "Étape secrète {$seq->index} du module {$module->order}"])
                ->create(['module_id' => $module->id]);
        });

    return $course->fresh();
}

it('serves checklist texts only for owned modules and never leaks locked ones', function () {
    $course = checklistCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $response = $this->actingAs($student)
        ->getJson("/api/courses/{$course->slug}/checklists")
        ->assertOk();

    $modules = collect($response->json('data.modules'));
    $ownedPayload = $modules->firstWhere('id', $owned->id);
    $lockedPayload = $modules->firstWhere('id', $locked->id);

    expect($ownedPayload['is_locked'])->toBeFalse()
        ->and($ownedPayload['items'])->toHaveCount(3)
        ->and($ownedPayload['items'][0])->toHaveKeys(['id', 'text', 'checked'])
        ->and($ownedPayload['items'][0]['checked'])->toBeFalse();

    // Locked module: the count teases, the texts never travel.
    expect($lockedPayload['is_locked'])->toBeTrue()
        ->and($lockedPayload['items'])->toBe([])
        ->and($lockedPayload['items_count'])->toBe(3)
        ->and(json_encode($lockedPayload))->not->toContain('Étape secrète');
});

it('lets a student check and uncheck a step, idempotently', function () {
    $course = checklistCourse();
    $module = $course->modules()->orderBy('order')->first();
    $item = $module->checklistItems()->first();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $module->id,
    ]);

    $this->actingAs($student)->putJson("/api/checklist-items/{$item->id}", ['checked' => true])
        ->assertOk()->assertJsonPath('data.checked', true);
    // Double-check stays a single row.
    $this->actingAs($student)->putJson("/api/checklist-items/{$item->id}", ['checked' => true])->assertOk();
    expect($item->checkedBy()->count())->toBe(1);

    // Reflected in the index payload.
    $payload = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/checklists")->json('data.modules');
    expect(collect($payload)->firstWhere('id', $module->id)['items'][0]['checked'])->toBeTrue();

    $this->actingAs($student)->putJson("/api/checklist-items/{$item->id}", ['checked' => false])->assertOk();
    expect($item->checkedBy()->count())->toBe(0);
});

it('refuses checking a step of a module the student does not own', function () {
    $course = checklistCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    // Guest first — actingAs() persists for the rest of the test.
    $this->putJson('/api/checklist-items/'.$owned->checklistItems()->first()->id, ['checked' => true])
        ->assertUnauthorized();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $this->actingAs($student)
        ->putJson('/api/checklist-items/'.$locked->checklistItems()->first()->id, ['checked' => true])
        ->assertForbidden();
});

it('manages personal tasks Google-Tasks style, scoped to their author', function () {
    $course = checklistCourse();
    $module = $course->modules()->orderBy('order')->first();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null, // full access
    ]);

    // Create
    $taskId = $this->actingAs($student)->postJson("/api/modules/{$module->id}/tasks", [
        'body' => 'Appeler le notaire pour le certificat négatif',
    ])->assertCreated()->json('data.id');

    // Toggle + edit
    $this->actingAs($student)->patchJson("/api/tasks/{$taskId}", ['is_done' => true])
        ->assertOk()->assertJsonPath('data.is_done', true);
    $this->actingAs($student)->patchJson("/api/tasks/{$taskId}", ['body' => 'Rappeler le notaire'])
        ->assertOk()->assertJsonPath('data.body', 'Rappeler le notaire');

    // Appears in the index, for its author only.
    $mine = collect($this->actingAs($student)->getJson("/api/courses/{$course->slug}/checklists")->json('data.modules'))
        ->firstWhere('id', $module->id);
    expect($mine['custom_tasks'])->toHaveCount(1);

    $other = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $other->id, 'course_id' => $course->id, 'module_id' => null]);
    $theirs = collect($this->actingAs($other)->getJson("/api/courses/{$course->slug}/checklists")->json('data.modules'))
        ->firstWhere('id', $module->id);
    expect($theirs['custom_tasks'])->toBe([]);

    // A stranger cannot edit or delete someone else's task.
    $this->actingAs($other)->patchJson("/api/tasks/{$taskId}", ['is_done' => false])->assertForbidden();
    $this->actingAs($other)->deleteJson("/api/tasks/{$taskId}")->assertForbidden();

    // The author deletes it.
    $this->actingAs($student)->deleteJson("/api/tasks/{$taskId}")->assertOk();
    expect(CustomTask::query()->count())->toBe(0);
});

it('keeps the curated checklists behind the paywall entirely', function () {
    $course = checklistCourse();

    $this->getJson("/api/courses/{$course->slug}/checklists")->assertUnauthorized();

    // Signed in, but holding no enrolment in this course: the module-level
    // locking below him is irrelevant, he must not reach the index at all.
    // Without the course-level gate every curated step — the paid substance
    // the rest of this controller works to protect — is one GET away.
    $stranger = User::factory()->create();
    $refused = $this->actingAs($stranger)
        ->getJson("/api/courses/{$course->slug}/checklists")
        ->assertForbidden();

    expect($refused->getContent())->not->toContain('Étape secrète');

    // An unknown slug is a 404, not a 403: route-model binding runs first, so
    // the gate never has to answer "does this course exist".
    $this->actingAs($stranger)->getJson('/api/courses/aucune-formation/checklists')->assertNotFound();
});

it('lets an admin drive any checklist but never author another student\'s tasks', function () {
    $course = checklistCourse();
    [$owned, $other] = $course->modules()->orderBy('order')->get();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);
    $studentTask = CustomTask::factory()->create([
        'user_id' => $student->id,
        'module_id' => $owned->id,
        'body' => 'Ma note personnelle.',
    ]);

    // The admin owns nothing here — support and content review need to see
    // every module, so isAdmin() bypasses the ownership check…
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson('/api/checklist-items/'.$other->checklistItems()->first()->id, ['checked' => true])
        ->assertOk();
    $this->actingAs($admin)
        ->postJson("/api/modules/{$other->id}/tasks", ['body' => 'Vérifier la rédaction de cette étape.'])
        ->assertCreated();

    // …and deliberately does NOT bypass authorship: a personal task list is
    // the student's own, so an admin may not tick it off or delete it. The
    // asymmetry is intentional; the obvious "consistency" refactor in either
    // direction has to fail here first.
    $this->actingAs($admin)->patchJson("/api/tasks/{$studentTask->id}", ['is_done' => true])->assertForbidden();
    $this->actingAs($admin)->deleteJson("/api/tasks/{$studentTask->id}")->assertForbidden();

    expect($studentTask->fresh()->is_done)->toBeFalse()
        ->and(CustomTask::query()->where('user_id', $student->id)->count())->toBe(1);
});

it('caps personal tasks at 100 per module and orders each new one after the last', function () {
    $course = checklistCourse();
    $module = $course->modules()->orderBy('order')->first();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);

    $create = fn (string $body) => $this->actingAs($student)
        ->postJson("/api/modules/{$module->id}/tasks", ['body' => $body]);

    $first = $create('Première note.')->assertCreated()->json('data.id');
    $create('Deuxième note.')->assertCreated();
    $create('Troisième note.')->assertCreated();

    $orders = fn () => CustomTask::query()
        ->where('user_id', $student->id)->where('module_id', $module->id)
        ->orderBy('id')->pluck('order')->all();

    expect($orders())->toBe([1, 2, 3]);

    // max(order) + 1, not count + 1: deleting from the middle of the list must
    // not make the next task collide with an existing position.
    $this->actingAs($student)->deleteJson("/api/tasks/{$first}")->assertOk();
    $create('Quatrième note.')->assertCreated();

    expect($orders())->toBe([2, 3, 4]);

    // The cap is the only thing between a personal to-do list and an unbounded
    // per-user write endpoint — nothing else rate-limits this route beyond the
    // global 120/min. Fill up to 99 rows, then walk the boundary.
    CustomTask::factory()->count(96)->create([
        'user_id' => $student->id,
        'module_id' => $module->id,
    ]);

    expect(CustomTask::query()->where('user_id', $student->id)->where('module_id', $module->id)->count())->toBe(99);

    $create('Centième note.')->assertCreated();
    $create('Cent-et-unième note.')
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect(CustomTask::query()->where('user_id', $student->id)->where('module_id', $module->id)->count())->toBe(100);

    // The cap is per (user, module): a second module still accepts tasks, and
    // so does another student in the module that is full.
    $second = $course->modules()->orderBy('order')->skip(1)->first();
    $this->actingAs($student)->postJson("/api/modules/{$second->id}/tasks", ['body' => 'Autre module.'])
        ->assertCreated();

    $neighbour = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $neighbour->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);
    $this->actingAs($neighbour)->postJson("/api/modules/{$module->id}/tasks", ['body' => 'Ma propre liste.'])
        ->assertCreated();
});

it('refuses tasks on unowned modules and enforces body validation', function () {
    $course = checklistCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $this->actingAs($student)->postJson("/api/modules/{$locked->id}/tasks", ['body' => 'x'])
        ->assertForbidden();

    $this->actingAs($student)->postJson("/api/modules/{$owned->id}/tasks", ['body' => ''])
        ->assertUnprocessable();
    $this->actingAs($student)->postJson("/api/modules/{$owned->id}/tasks", ['body' => str_repeat('a', 501)])
        ->assertUnprocessable();
});
