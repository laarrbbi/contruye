<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * A published course whose only module is paid content and carries a legal
 * block — the material the ficha de actuación PDF embeds alongside the
 * lesson's own blocks.
 *
 * @return array{0: Course, 1: Module}
 */
function paidModuleWithLegalBlock(): array
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 490]);
    $module = Module::factory()->for($course)->create(['order' => 1, 'price_eur' => 49]);

    $module->contentBlocks()->create([
        'order' => 1,
        'type' => 'legal',
        'body_es' => 'Real Decreto 1627/1997 — seguridad y salud en las obras de construcción.',
    ]);

    return [$course, $module];
}

it('prints the reflex sheet of a free-preview lesson for a visitor who bought nothing', function () {
    [$course, $module] = paidModuleWithLegalBlock();

    $preview = Lesson::factory()->for($module)->create([
        'order' => 1,
        'is_free_preview' => true,
        'is_reflex_sheet' => true,
    ]);
    // Same module, same embedded legal blocks — only the preview flag differs.
    $ordinary = Lesson::factory()->for($module)->create([
        'order' => 2,
        'is_free_preview' => false,
        'is_reflex_sheet' => true,
    ]);

    $this->getJson("/api/lessons/{$preview->id}/reflex-sheet")->assertUnauthorized();

    // No enrolment of any kind — not in this course, not in any other.
    $stranger = User::factory()->create();

    expect(Enrollment::query()->where('user_id', $stranger->id)->count())->toBe(0);

    // The gate is authorizeLessonAccess(..., allowFreePreview: true), so the
    // preview flag alone opens the PDF, and the PDF carries the LOCKED
    // module's legal block. Findings S6/C14: knowingly left as it is — the
    // sheet exists to be printed and pinned up in the site office, so
    // reaching people who have not paid is the point. Pinned here because it
    // was decided rather than overlooked: flipping the flag to false must
    // break a test instead of passing unnoticed.
    $response = $this->actingAs($stranger)
        ->get("/api/lessons/{$preview->id}/reflex-sheet")
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');

    // Without the preview flag the paywall holds, for the very same content.
    $this->actingAs($stranger)
        ->get("/api/lessons/{$ordinary->id}/reflex-sheet")
        ->assertForbidden();
});

it('prints a ficha de actuación only for a lesson written as one', function () {
    [$course, $module] = paidModuleWithLegalBlock();

    $ordinary = Lesson::factory()->for($module)->create([
        'order' => 1,
        'is_free_preview' => false,
        'is_reflex_sheet' => false,
    ]);
    $sheet = Lesson::factory()->for($module)->create([
        'order' => 2,
        'is_free_preview' => false,
        'is_reflex_sheet' => true,
    ]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);

    // The player offers the download on has_reflex_sheet…
    $lessons = collect(collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    )->firstWhere('id', $module->id)['lessons']);

    expect($lessons->firstWhere('id', $ordinary->id)['has_reflex_sheet'])->toBeFalse()
        ->and($lessons->firstWhere('id', $sheet->id)['has_reflex_sheet'])->toBeTrue();

    // …and the endpoint now agrees with it. Finding C56, closed.
    $this->actingAs($student)->get("/api/lessons/{$ordinary->id}/reflex-sheet")->assertNotFound();

    $response = $this->actingAs($student)->get("/api/lessons/{$sheet->id}/reflex-sheet")->assertOk();

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($response->headers->get('content-disposition'))->toContain("ficha-actuacion-{$sheet->id}.pdf");
});
