<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The shape that separates the two access rules the player applies:
 * PLAYABLE (module owned OR lesson is a free preview) and OWNED (module
 * bought). Module 1 is owned, module 2 is not — everything below is about one
 * of those rules being read where the other one belongs.
 *
 * @return array{0: Course, 1: Module, 2: Module, 3: User}
 */
function contentGateCourse(): array
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 90000]);

    $owned = Module::factory()->for($course)->create(['order' => 1, 'price_eur' => 12000]);
    $locked = Module::factory()->for($course)->create(['order' => 2, 'price_eur' => 12000]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    return [$course->fresh(), $owned, $locked, $student];
}

it('empties every carrier of the written curriculum in a locked lesson', function () {
    [$course, $owned, $locked, $student] = contentGateCourse();

    $paid = Lesson::factory()->for($locked)->create([
        'order' => 1,
        'content_es' => 'Le montage financier complet d\'une opération VSP.',
        'key_points_es' => ['Garantie CGCI obligatoire', 'Apport minimum de 30 %'],
        'is_reflex_sheet' => true,
    ]);
    $paid->contentBlocks()->create([
        'order' => 1,
        'type' => 'legal',
        'body_es' => 'Décret exécutif 13-431 du 18 décembre 2013.',
    ]);

    $preview = Lesson::factory()->freePreview()->for($locked)->create([
        'order' => 2,
        'content_es' => 'Ce que couvre ce module.',
        'key_points_es' => ['Aperçu offert'],
        'is_reflex_sheet' => true,
    ]);
    $preview->contentBlocks()->create([
        'order' => 1,
        'type' => 'text',
        'body_es' => 'Extrait offert.',
    ]);

    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    );

    $lockedPayload = $modules->firstWhere('id', $locked->id);
    $lessons = collect($lockedPayload['lessons']);
    $paidPayload = $lessons->firstWhere('id', $paid->id);
    $previewPayload = $lessons->firstWhere('id', $preview->id);

    // The paid lesson is teased by title and duration only. `is_locked` alone
    // is a hint to the UI, not a gate: the payload must carry nothing to leak.
    expect($paidPayload['is_locked'])->toBeTrue()
        ->and($paidPayload['title'])->toBe($paid->title_es)
        ->and($paidPayload['content'])->toBeNull()
        ->and($paidPayload['key_points'])->toBe([])
        ->and($paidPayload['blocks'])->toBe([])
        ->and($paidPayload['has_reflex_sheet'])->toBeFalse();

    // ...while the free preview sitting in the SAME locked module keeps its
    // content, so the gate stays playability and never collapses into
    // ownership — which would silently take the teaser away.
    expect($previewPayload['is_locked'])->toBeFalse()
        ->and($previewPayload['content'])->toBe('Ce que couvre ce module.')
        ->and($previewPayload['key_points'])->toBe(['Aperçu offert'])
        ->and($previewPayload['has_reflex_sheet'])->toBeTrue()
        ->and(collect($previewPayload['blocks'])->pluck('body')->all())->toBe(['Extrait offert.']);

    // Nothing of the paid lesson survives anywhere under the locked module.
    $json = json_encode($lockedPayload);

    expect($json)->not->toContain('montage financier')
        ->and($json)->not->toContain('Garantie CGCI')
        ->and($json)->not->toContain('Décret exécutif 13-431');
});

it('refuses the attachments of a free-preview lesson inside an unowned module', function () {
    config([
        'services.bunny.library_id' => '123456',
        'services.bunny.token_key' => 'test-token-key',
        'services.bunny.embed_ttl' => 14400,
    ]);
    Storage::fake('local');

    [$course, $owned, $locked, $student] = contentGateCourse();

    $preview = Lesson::factory()->freePreview()->for($locked)->create([
        'order' => 1,
        'attachments' => [['name' => 'Modele-contrat-VSP.pdf', 'path' => 'lessons/attachments/vsp.pdf']],
    ]);
    $ownedLesson = Lesson::factory()->for($owned)->create([
        'order' => 1,
        'attachments' => [['name' => 'Checklist-chantier.pdf', 'path' => 'lessons/attachments/chantier.pdf']],
    ]);

    Storage::disk('local')->put('lessons/attachments/vsp.pdf', 'paid attachment body');
    Storage::disk('local')->put('lessons/attachments/chantier.pdf', 'owned attachment body');

    // Playable and paid at once: the video streams, the file does not. A free
    // preview is a taste of the lesson, not a key to the module's deliverables.
    $this->actingAs($student)->getJson("/api/lessons/{$preview->id}/playback")->assertOk();
    $this->actingAs($student)->getJson("/api/lessons/{$preview->id}/attachments/0")->assertForbidden();

    // The same student's own module hands its file over normally, so the 403
    // above is about ownership and not about the fixture.
    $this->actingAs($student)->get("/api/lessons/{$ownedLesson->id}/attachments/0")
        ->assertOk()
        ->assertDownload('Checklist-chantier.pdf');

    // The gate runs before the index lookup, so an out-of-range index on an
    // unowned lesson answers 403 and cannot be used to probe what exists.
    $this->actingAs($student)->getJson("/api/lessons/{$preview->id}/attachments/9")->assertForbidden();

    // The player payload agrees: the count teases, the filename stays back.
    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    );
    $lockedPayload = $modules->firstWhere('id', $locked->id);
    $previewPayload = collect($lockedPayload['lessons'])->firstWhere('id', $preview->id);

    expect($previewPayload['attachments'])->toBe([])
        ->and($previewPayload['attachments_count'])->toBe(1)
        ->and(json_encode($lockedPayload))->not->toContain('Modele-contrat-VSP.pdf');
});

it('serves module-level legal and expert-tip blocks only to owners', function () {
    [$course, $owned, $locked, $student] = contentGateCourse();

    foreach ([$owned, $locked] as $module) {
        $module->contentBlocks()->create([
            'order' => 1,
            'type' => 'legal',
            'body_es' => 'Loi 11-04 du 17 février 2011 — article 26.',
        ]);
        $module->contentBlocks()->create([
            'order' => 2,
            'type' => 'expert_tip',
            'body_es' => 'Ne signez jamais avant l\'obtention du permis de construire.',
        ]);
    }

    // A free-preview lesson makes the locked module partly playable — the
    // MODULE-level blocks must still follow ownership regardless.
    Lesson::factory()->freePreview()->for($locked)->create(['order' => 1]);

    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    );

    $ownedPayload = $modules->firstWhere('id', $owned->id);
    $lockedPayload = $modules->firstWhere('id', $locked->id);

    expect(collect($ownedPayload['blocks'])->pluck('type')->all())->toBe(['legal', 'expert_tip'])
        ->and(collect($ownedPayload['blocks'])->pluck('body')->all())
        ->toContain('Loi 11-04 du 17 février 2011 — article 26.')
        ->and($lockedPayload['blocks'])->toBe([]);

    // partner_slot is not the only block type the lock has to cover: the
    // statutory references and the practitioner advice are paid content too.
    expect(json_encode($lockedPayload))->not->toContain('Loi 11-04')
        ->and(json_encode($lockedPayload))->not->toContain('permis de construire');
});
