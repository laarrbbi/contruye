<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * The v2 content pass is French-first by design: the migration made title_en
 * nullable and every seeded row has it NULL. The request classes still
 * required it, so the admin form loaded a null, sent it back, and every module
 * and lesson in the catalogue refused to save.
 */
it('lets an admin edit a module whose Arabic title has not been written yet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create([
        'order' => 1, 'title_es' => 'Le foncier', 'title_en' => null,
    ]);

    $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}", [
        'title_es' => 'Le foncier, corrigé',
        'title_en' => null,
    ])->assertOk();

    expect($module->fresh()->title_es)->toBe('Le foncier, corrigé')
        ->and($module->fresh()->title_en)->toBeNull()
        // French stands in until a translation exists — that is the whole point.
        ->and($module->fresh()->localizedTitle('ar'))->toBe('Le foncier, corrigé');
});

it('lets an admin edit a lesson whose Arabic title has not been written yet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lesson = Lesson::factory()
        ->for(Module::factory()->for(Course::factory())->create(['order' => 1]))
        ->create(['order' => 1, 'title_es' => 'Choisir la forme', 'title_en' => null]);

    $this->actingAs($admin)->putJson("/api/admin/lessons/{$lesson->id}", [
        'title_es' => 'Choisir la forme juridique',
        'title_en' => null,
    ])->assertOk();

    expect($lesson->fresh()->title_es)->toBe('Choisir la forme juridique');
});

it('still accepts an Arabic title when one is written', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create(['order' => 1]);

    $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}", [
        'title_es' => 'Le foncier',
        'title_en' => 'العقار',
    ])->assertOk();

    expect($module->fresh()->localizedTitle('ar'))->toBe('العقار');
});
