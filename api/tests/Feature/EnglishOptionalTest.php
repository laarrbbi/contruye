<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * The content is Spanish-first by design: title_en is nullable and every
 * seeded row has it NULL. On the platform this one was adapted from, the
 * request classes still required the second language, so the admin form
 * loaded a null, sent it back, and every module and lesson in the catalogue
 * refused to save.
 */
it('lets an admin edit a module whose English title has not been written yet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create([
        'order' => 1, 'title_es' => 'El solar', 'title_en' => null,
    ]);

    $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}", [
        'title_es' => 'El solar, corregido',
        'title_en' => null,
    ])->assertOk();

    expect($module->fresh()->title_es)->toBe('El solar, corregido')
        ->and($module->fresh()->title_en)->toBeNull()
        // Spanish stands in until a translation exists — that is the whole point.
        ->and($module->fresh()->localizedTitle('en'))->toBe('El solar, corregido');
});

it('lets an admin edit a lesson whose English title has not been written yet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lesson = Lesson::factory()
        ->for(Module::factory()->for(Course::factory())->create(['order' => 1]))
        ->create(['order' => 1, 'title_es' => 'Elegir la forma', 'title_en' => null]);

    $this->actingAs($admin)->putJson("/api/admin/lessons/{$lesson->id}", [
        'title_es' => 'Elegir la forma jurídica',
        'title_en' => null,
    ])->assertOk();

    expect($lesson->fresh()->title_es)->toBe('Elegir la forma jurídica');
});

it('still accepts an English title when one is written', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $module = Module::factory()->for(Course::factory())->create(['order' => 1]);

    $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}", [
        'title_es' => 'El solar',
        'title_en' => 'The site',
    ])->assertOk();

    expect($module->fresh()->localizedTitle('en'))->toBe('The site');
});
