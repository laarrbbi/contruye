<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Partner;
use App\Models\User;
use App\Models\Wilaya;

function wilaya(int $code): Wilaya
{
    return Wilaya::query()->where('code', $code)->firstOrFail();
}

it('exposes the 58 wilayas as public reference data', function () {
    $response = $this->getJson('/api/wilayas')->assertOk();

    expect($response->json('data'))->toHaveCount(58);

    $first = $response->json('data.0');
    $last = $response->json('data.57');

    expect($first['code'])->toBe(1)
        ->and($first['name_fr'])->toBe('Adrar')
        ->and($last['code'])->toBe(58)
        ->and($last['name_fr'])->toBe('El Meniaa')
        // Matricule order, not alphabetical: 16 is Alger.
        ->and($response->json('data.15.name_fr'))->toBe('Alger')
        ->and($response->json('data.15.name_ar'))->toBe('الجزائر');
});

it('caches the wilaya list privately for a day and varies it on the language', function () {
    $response = $this->getJson('/api/wilayas')->assertOk();

    $cacheControl = $response->headers->get('Cache-Control');

    // Worth caching for a day — the list has not changed since 2019. But
    // PRIVATE: this is a stateful API that may attach a session cookie, and a
    // shared cache storing the response would serve one learner's session to
    // the next visitor behind the same proxy.
    expect($cacheControl)->toContain('private')
        ->and($cacheControl)->toContain('max-age=86400')
        ->and($cacheControl)->not->toContain('public')
        // `name` is localized, so a cache keyed on the URL alone would hand
        // Arabic names to a French reader. Appended, because CORS already
        // claimed Vary for Origin — overwriting it would break the CORS cache.
        ->and($response->headers->get('Vary'))->toContain('Accept-Language')
        ->and($response->headers->get('Vary'))->toContain('Origin');

    // The Vary is not decorative: the body really does change with the header.
    expect($this->getJson('/api/wilayas', ['Accept-Language' => 'ar'])->json('data.15.name'))->toBe('الجزائر')
        ->and($this->getJson('/api/wilayas', ['Accept-Language' => 'fr'])->json('data.15.name'))->toBe('Alger');
});

it('matches partners by explicit coverage and treats national ones as everywhere', function () {
    $alger = wilaya(16);
    $oran = wilaya(31);
    $setif = wilaya(19);

    $notary = Partner::factory()->create(['name' => 'Notaire Alger-Blida']);
    $notary->wilayas()->sync([$alger->id, wilaya(9)->id]);

    $bank = Partner::factory()->national()->create(['name' => 'Banque nationale']);
    $oranOnly = Partner::factory()->create(['name' => 'Consultant Oran']);
    $oranOnly->wilayas()->sync([$oran->id]);

    expect(Partner::inWilaya($alger->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['Notaire Alger-Blida', 'Banque nationale'])
        ->and(Partner::inWilaya($oran->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['Consultant Oran', 'Banque nationale'])
        // Nobody local in Sétif: only the national partner is there.
        ->and(Partner::inWilaya($setif->id)->pluck('name')->all())
        ->toBe(['Banque nationale']);

    expect($notary->coversWilaya($alger->id))->toBeTrue()
        ->and($notary->coversWilaya($setif->id))->toBeFalse()
        ->and($bank->coversWilaya($setif->id))->toBeTrue();
});

it('lets an admin set, replace and clear a partner coverage', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $alger = wilaya(16);
    $oran = wilaya(31);

    $id = $this->actingAs($admin)->postJson('/api/admin/partners', [
        'name' => 'Bureau d\'études',
        'category' => 'bureau_etudes',
        'wilaya_ids' => [$alger->id, $oran->id],
    ])->assertCreated()->json('data.id');

    expect(Partner::find($id)->wilayas->pluck('code')->all())->toBe([16, 31]);

    // Replacing the list removes what is no longer covered.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Bureau d\'études',
        'category' => 'bureau_etudes',
        'wilaya_ids' => [$oran->id],
    ])->assertOk()->assertJsonPath('data.wilayas.0.code', 31);

    expect(Partner::find($id)->wilayas)->toHaveCount(1);

    // Switching to national wipes the explicit rows: the flag is the truth.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Bureau d\'études',
        'category' => 'bureau_etudes',
        'is_national' => true,
        'wilaya_ids' => [$oran->id],
    ])->assertOk()->assertJsonPath('data.is_national', true);

    expect(Partner::find($id)->wilayas)->toHaveCount(0);

    // Naming wilayas again takes the partner back off national coverage, even
    // without restating the flag — the list is the intent.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Bureau d\'études',
        'category' => 'bureau_etudes',
        'wilaya_ids' => [$alger->id],
    ])->assertOk()->assertJsonPath('data.is_national', false);

    expect(Partner::find($id)->wilayas->pluck('code')->all())->toBe([16]);

    // An unknown wilaya is refused rather than silently dropped.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Bureau d\'études',
        'category' => 'bureau_etudes',
        'wilaya_ids' => [999999],
    ])->assertUnprocessable()->assertJsonValidationErrors(['wilaya_ids.0']);
});

it('filters the admin list by wilaya and names the categories missing there', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $setif = wilaya(19);

    $local = Partner::factory()->create(['name' => 'Notaire Sétif', 'category' => 'notaire']);
    $local->wilayas()->sync([$setif->id]);
    $bank = Partner::factory()->national()->create(['name' => 'Banque', 'category' => 'banque']);
    $elsewhere = Partner::factory()->create(['name' => 'Notaire Oran', 'category' => 'notaire']);
    $elsewhere->wilayas()->sync([wilaya(31)->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/partners?wilaya_id={$setif->id}")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toEqualCanonicalizing(['Notaire Sétif', 'Banque'])
        // notaire and banque are covered; every other trade is a gap.
        ->and($response->json('meta.gaps'))
        ->toEqualCanonicalizing(['expert_comptable', 'bureau_etudes', 'marketing', 'consultant_agrement', 'autre']);

    expect($bank->fresh()->is_national)->toBeTrue();

    // Without the filter there is no gap analysis to report.
    $this->actingAs($admin)->getJson('/api/admin/partners')
        ->assertOk()
        ->assertJsonPath('meta', null);
});

it('lets a learner save their wilaya on their profile', function () {
    $user = User::factory()->create();
    $blida = wilaya(9);

    $this->getJson('/api/me')->assertUnauthorized();

    $this->actingAs($user)->patchJson('/api/me', ['wilaya_id' => $blida->id])
        ->assertOk()
        ->assertJsonPath('data.wilaya_id', $blida->id)
        ->assertJsonPath('data.wilaya.name', 'Blida');

    expect($user->fresh()->wilaya_id)->toBe($blida->id);

    // Clearing it is allowed; an unknown wilaya and a role change are not.
    $this->actingAs($user)->patchJson('/api/me', ['wilaya_id' => null])->assertOk();
    expect($user->fresh()->wilaya_id)->toBeNull();

    $this->actingAs($user)->patchJson('/api/me', ['wilaya_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors(['wilaya_id']);

    $this->actingAs($user)->patchJson('/api/me', ['role' => 'admin'])->assertOk();
    expect($user->fresh()->role)->toBe('student');
});

it('serialises partner coverage to owners and never leaks it through a free preview', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $owned = Module::factory()->for($course)->create(['order' => 1]);
    $locked = Module::factory()->for($course)->create(['order' => 2]);

    $partner = Partner::factory()->create([
        'name' => 'Notaire secret',
        'category' => 'notaire',
        'phone' => '0550999888',
    ]);
    $partner->wilayas()->sync([wilaya(16)->id, wilaya(9)->id]);

    foreach ([$owned, $locked] as $module) {
        $module->partners()->attach($partner->id, ['order' => 1]);
        $module->contentBlocks()->create([
            'order' => 1,
            'type' => 'partner_slot',
            'partner_category' => 'notaire',
            'body_fr' => 'Notaire recommandé.',
        ]);
    }

    // The locked module still has a free-preview lesson — playable, but it
    // must not hand out the paid contacts.
    Lesson::factory()->for($locked)->create(['order' => 1, 'is_free_preview' => true]);
    $previewBlockLesson = Lesson::factory()->for($locked)->create(['order' => 2, 'is_free_preview' => true]);
    $previewBlockLesson->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notaire',
        'body_fr' => 'Notaire recommandé.',
    ]);

    $student = User::factory()->create();
    Enrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $json = $this->actingAs($student)
        ->getJson("/api/courses/{$course->slug}/learn")
        ->assertOk();

    $ownedPayload = collect($json->json('data.modules'))->firstWhere('id', $owned->id);
    $lockedPayload = collect($json->json('data.modules'))->firstWhere('id', $locked->id);

    expect($ownedPayload['partners'][0]['wilayas'])->toHaveCount(2)
        ->and($ownedPayload['partners'][0]['wilayas'][0]['code'])->toBe(9)
        ->and($ownedPayload['partners'][0]['is_national'])->toBeFalse()
        ->and($lockedPayload['partners'])->toBe([])
        ->and($lockedPayload['partners_count'])->toBe(1);

    // The free-preview lesson of the locked module ships the slot with the
    // count only — no phone number anywhere in the response.
    $previewLesson = collect($lockedPayload['lessons'])->firstWhere('id', $previewBlockLesson->id);
    $slot = collect($previewLesson['blocks'])->firstWhere('type', 'partner_slot');

    // Nothing anywhere under the locked module carries the contact — the
    // owned module legitimately does, which is why the check is scoped.
    $lockedJson = json_encode($lockedPayload);

    expect($slot['partners'])->toBe([])
        ->and($slot['partners_count'])->toBe(1)
        ->and($lockedJson)->not->toContain('0550999888')
        ->and($lockedJson)->not->toContain('Notaire secret');
});

it('teases the partner count on a locked slot instead of hiding it entirely', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $locked = Module::factory()->for($course)->create(['order' => 1]);
    // A second, owned module: the learner is enrolled in the course but not in
    // the module holding the slot.
    $owned = Module::factory()->for($course)->create(['order' => 2]);

    $partner = Partner::factory()->create(['category' => 'notaire', 'phone' => '0550111222']);
    $locked->partners()->attach($partner->id, ['order' => 1]);
    $locked->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notaire',
        'body_fr' => 'Notaire recommandé.',
    ]);
    $preview = Lesson::factory()->for($locked)->create(['order' => 1, 'is_free_preview' => true]);
    $preview->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notaire',
        'body_fr' => 'Notaire recommandé.',
    ]);

    $student = User::factory()->create();
    Enrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    $json = $this->actingAs($student)
        ->getJson("/api/courses/{$course->slug}/learn")
        ->assertOk();

    $module = collect($json->json('data.modules'))->firstWhere('id', $locked->id);
    $lesson = collect($module['lessons'])->firstWhere('id', $preview->id);
    $slot = collect($lesson['blocks'])->firstWhere('type', 'partner_slot');

    // Withheld, but counted — the UI can say what the module holds without
    // pretending it holds nothing.
    expect($slot['partners'])->toBe([])
        ->and($slot['partners_count'])->toBe(1)
        ->and(json_encode($module))->not->toContain('0550111222');
});

it('rejects malformed partner list filters instead of failing with a 500', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin/partners?search[]=x')
        ->assertUnprocessable()->assertJsonValidationErrors(['search']);

    $this->actingAs($admin)->getJson('/api/admin/partners?wilaya_id=999999')
        ->assertUnprocessable()->assertJsonValidationErrors(['wilaya_id']);

    // A literal % must stay a literal, not a wildcard matching everything.
    Partner::factory()->create(['name' => 'Cabinet Normal']);
    $this->actingAs($admin)->getJson('/api/admin/partners?search=%')
        ->assertOk()->assertJsonCount(0, 'data');
});
