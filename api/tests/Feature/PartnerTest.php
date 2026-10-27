<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Partner;
use App\Models\User;
use App\Models\Wilaya;

/** Course with two modules, each holding one lesson. */
function partnerCourse(): Course
{
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);

    Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40])
        ->each(fn (Module $module) => Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]));

    return $course->fresh();
}

it('lists partners only for modules the student owns', function () {
    $course = partnerCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    $partnerA = Partner::factory()->create(['name' => 'Notaire Bensalem', 'category' => 'notaire']);
    $partnerB = Partner::factory()->create(['name' => 'Cabinet Compta DZ', 'category' => 'expert_comptable']);
    $owned->partners()->attach($partnerA->id, ['order' => 1, 'note_fr' => 'Pour vos actes VSP']);
    $locked->partners()->attach($partnerB->id, ['order' => 1]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.modules')
    );

    $ownedPayload = $modules->firstWhere('id', $owned->id);
    $lockedPayload = $modules->firstWhere('id', $locked->id);

    // Owned module: full details, including the per-module note.
    expect($ownedPayload['partners'])->toHaveCount(1)
        ->and($ownedPayload['partners'][0]['name'])->toBe('Notaire Bensalem')
        ->and($ownedPayload['partners'][0]['phone'])->not->toBeNull()
        ->and($ownedPayload['partners'][0]['note'])->toBe('Pour vos actes VSP');

    // Locked module: no contact details leak — only the count teases them.
    expect($lockedPayload['is_locked'])->toBeTrue()
        ->and($lockedPayload['partners'])->toBe([])
        ->and($lockedPayload['partners_count'])->toBe(1);

    // Nothing about partner B is reachable anywhere in the locked payload.
    expect(json_encode($lockedPayload))->not->toContain('Cabinet Compta DZ')
        ->and(json_encode($lockedPayload))->not->toContain($partnerB->phone)
        ->and(json_encode($lockedPayload))->not->toContain($partnerB->email);
});

it('hides deactivated partners from students but keeps the count honest', function () {
    $course = partnerCourse();
    $module = $course->modules()->orderBy('order')->first();

    $active = Partner::factory()->create(['name' => 'Banque Active']);
    $inactive = Partner::factory()->inactive()->create(['name' => 'Ancien Partenaire']);
    $module->partners()->attach([$active->id => ['order' => 1], $inactive->id => ['order' => 2]]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null, // full access
    ]);

    $payload = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->json('data.modules')
    )->firstWhere('id', $module->id);

    expect($payload['partners'])->toHaveCount(1)
        ->and($payload['partners'][0]['name'])->toBe('Banque Active')
        ->and($payload['partners_count'])->toBe(1)
        ->and(json_encode($payload))->not->toContain('Ancien Partenaire');
});

it('serves lesson content only for accessible or free-preview lessons', function () {
    $course = partnerCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    $ownedLesson = $owned->lessons()->first();
    $ownedLesson->update(['content_fr' => 'Checklist du dossier CNRC', 'content_ar' => 'قائمة ملف CNRC']);

    $lockedLesson = $locked->lessons()->first();
    $lockedLesson->update(['content_fr' => 'Contenu payant réservé']);

    $preview = Lesson::factory()->create([
        'module_id' => $locked->id,
        'order' => 2,
        'is_free_preview' => true,
        'content_fr' => 'Extrait gratuit',
    ]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->json('data.modules')
    );

    $lessons = $modules->flatMap(fn ($m) => $m['lessons'])->keyBy('id');

    expect($lessons[$ownedLesson->id]['content'])->toBe('Checklist du dossier CNRC')
        ->and($lessons[$lockedLesson->id]['content'])->toBeNull()   // paid content withheld
        ->and($lessons[$preview->id]['content'])->toBe('Extrait gratuit'); // free preview shown
});

it('returns lesson content in the requested language', function () {
    $course = partnerCourse();
    $module = $course->modules()->orderBy('order')->first();
    $lesson = $module->lessons()->first();
    $lesson->update(['content_fr' => 'Contenu FR', 'content_ar' => 'محتوى عربي']);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);

    $ar = $this->actingAs($student)
        ->withHeader('Accept-Language', 'ar')
        ->getJson("/api/courses/{$course->slug}/learn")->json('data.modules.0.lessons.0.content');

    expect($ar)->toBe('محتوى عربي');
});

it('lets an admin manage partners and attach them to a module in order', function () {
    $course = partnerCourse();
    $module = $course->modules()->orderBy('order')->first();
    $admin = User::factory()->create(['role' => 'admin']);

    $id = $this->actingAs($admin)->postJson('/api/admin/partners', [
        'name' => 'Étude Notariale Alger',
        'category' => 'notaire',
        'description_fr' => 'Actes VSP et livrets fonciers.',
        'website' => 'https://notaire.example.dz',
        'phone' => '0550112233',
        'wilaya_ids' => [Wilaya::where('code', 16)->value('id')],
    ])->assertCreated()->json('data.id');

    // Unknown category is refused.
    $this->actingAs($admin)->postJson('/api/admin/partners', [
        'name' => 'X', 'category' => 'crypto_guru',
    ])->assertUnprocessable()->assertJsonValidationErrors(['category']);

    // A malformed website is refused.
    $this->actingAs($admin)->postJson('/api/admin/partners', [
        'name' => 'X', 'category' => 'banque', 'website' => 'not-a-url',
    ])->assertUnprocessable()->assertJsonValidationErrors(['website']);

    $second = Partner::factory()->create();

    // Attach both, in a deliberate order.
    $synced = $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}/partners", [
        'partners' => [
            ['partner_id' => $second->id, 'note_fr' => 'Financement acquéreurs'],
            ['partner_id' => $id, 'note_fr' => 'Actes de vente'],
        ],
    ])->assertOk()->json('data');

    expect($synced)->toHaveCount(2)
        ->and($synced[0]['partner_id'])->toBe($second->id)
        ->and($synced[0]['order'])->toBe(1)
        ->and($synced[1]['partner_id'])->toBe($id)
        ->and($synced[1]['order'])->toBe(2);

    // Syncing with an empty list detaches everything.
    $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}/partners", ['partners' => []])
        ->assertOk();
    expect($module->fresh()->partners()->count())->toBe(0);

    $this->actingAs($admin)->deleteJson("/api/admin/partners/{$id}")->assertOk();
    $this->assertDatabaseMissing('partners', ['id' => $id]);
});

it('lets an admin write lesson content in both languages', function () {
    $course = partnerCourse();
    $lesson = $course->modules()->orderBy('order')->first()->lessons()->first();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->putJson("/api/admin/lessons/{$lesson->id}", [
        'title_fr' => $lesson->title_fr,
        'title_ar' => $lesson->title_ar,
        'content_fr' => 'Étapes détaillées du dépôt en wilaya.',
        'content_ar' => 'خطوات الإيداع في الولاية.',
    ])->assertOk()
        ->assertJsonPath('data.content_fr', 'Étapes détaillées du dépôt en wilaya.')
        ->assertJsonPath('data.content_ar', 'خطوات الإيداع في الولاية.');

    expect($lesson->fresh()->content_fr)->toBe('Étapes détaillées du dépôt en wilaya.');
});

it('forbids partner management for non-admins', function () {
    $course = partnerCourse();
    $module = $course->modules()->first();
    $student = User::factory()->create();

    $this->actingAs($student)->getJson('/api/admin/partners')->assertForbidden();
    $this->actingAs($student)->postJson('/api/admin/partners', [
        'name' => 'X', 'category' => 'notaire',
    ])->assertForbidden();
    $this->actingAs($student)->putJson("/api/admin/modules/{$module->id}/partners", ['partners' => []])
        ->assertForbidden();
});

it('tracks progress on a free-preview lesson inside an unowned module', function () {
    $course = partnerCourse();
    [$owned, $locked] = $course->modules()->orderBy('order')->get();

    $preview = Lesson::factory()->create([
        'module_id' => $locked->id,
        'order' => 2,
        'is_free_preview' => true,
    ]);

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    // The payload presents the preview as unlocked, so the endpoints that
    // follow playback must accept it rather than silently 403.
    $this->actingAs($student)
        ->postJson("/api/lessons/{$preview->id}/progress", ['position_seconds' => 42])
        ->assertOk();
    $this->actingAs($student)
        ->postJson("/api/lessons/{$preview->id}/complete", ['completed' => true])
        ->assertOk();

    // …and that progress round-trips instead of being discarded.
    $lessons = collect($this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->json('data.modules'))
        ->flatMap(fn ($m) => $m['lessons'])->keyBy('id');

    expect($lessons[$preview->id]['is_completed'])->toBeTrue();

    // A genuinely locked (non-preview) lesson is still refused.
    $paid = $locked->lessons()->where('is_free_preview', false)->first();
    $this->actingAs($student)
        ->postJson("/api/lessons/{$paid->id}/progress", ['position_seconds' => 10])
        ->assertForbidden();
});

it('orders synced partners by list position, ignoring client array keys', function () {
    $course = partnerCourse();
    $module = $course->modules()->orderBy('order')->first();
    $admin = User::factory()->create(['role' => 'admin']);

    $a = Partner::factory()->create(['name' => 'Premier']);
    $b = Partner::factory()->create(['name' => 'Second']);

    // Sparse / non-sequential keys must not become the display order.
    $synced = $this->actingAs($admin)->putJson("/api/admin/modules/{$module->id}/partners", [
        'partners' => [
            10 => ['partner_id' => $a->id],
            2 => ['partner_id' => $b->id],
        ],
    ])->assertOk()->json('data');

    expect($synced[0]['order'])->toBe(1)
        ->and($synced[1]['order'])->toBe(2)
        ->and($synced[0]['partner_id'])->toBe($a->id);
});

it('exposes a module\'s partners to the admin content editor', function () {
    $course = partnerCourse();
    $module = $course->modules()->orderBy('order')->first();
    $admin = User::factory()->create(['role' => 'admin']);
    $partner = Partner::factory()->create(['name' => 'Notaire Test']);
    $module->partners()->attach($partner->id, ['order' => 1, 'note_fr' => 'Note admin']);

    $payload = collect($this->actingAs($admin)->getJson("/api/admin/courses/{$course->id}")->assertOk()
        ->json('data.modules'))->firstWhere('id', $module->id);

    expect($payload['partners'])->toHaveCount(1)
        ->and($payload['partners'][0]['name'])->toBe('Notaire Test')
        ->and($payload['partners'][0]['note_fr'])->toBe('Note admin');
});
