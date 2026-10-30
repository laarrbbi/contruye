<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Partner;
use App\Models\Province;
use App\Models\User;

function province(int $code): Province
{
    return Province::query()->where('code', $code)->firstOrFail();
}

it('exposes the 52 provinces as public reference data', function () {
    $response = $this->getJson('/api/provinces')->assertOk();

    expect($response->json('data'))->toHaveCount(52);

    $first = $response->json('data.0');
    $last = $response->json('data.51');

    expect($first['code'])->toBe(1)
        ->and($first['name_es'])->toBe('Araba/Álava')
        ->and($last['code'])->toBe(52)
        ->and($last['name_es'])->toBe('Melilla')
        // INE code order, not alphabetical: 28 is Madrid, 41 Sevilla.
        ->and($response->json('data.27.name_es'))->toBe('Madrid')
        ->and($response->json('data.40.name_es'))->toBe('Sevilla')
        ->and($response->json('data.40.name_en'))->toBe('Seville');
});

it('caches the province list privately for a day and varies it on the language', function () {
    $response = $this->getJson('/api/provinces')->assertOk();

    $cacheControl = $response->headers->get('Cache-Control');

    // Worth caching for a day — the list has not changed since 1927. But
    // PRIVATE: this is a stateful API that may attach a session cookie, and a
    // shared cache storing the response would serve one learner's session to
    // the next visitor behind the same proxy.
    expect($cacheControl)->toContain('private')
        ->and($cacheControl)->toContain('max-age=86400')
        ->and($cacheControl)->not->toContain('public')
        // `name` is localized, so a cache keyed on the URL alone would hand
        // English names to a Spanish reader. Appended, because CORS already
        // claimed Vary for Origin — overwriting it would break the CORS cache.
        ->and($response->headers->get('Vary'))->toContain('Accept-Language')
        ->and($response->headers->get('Vary'))->toContain('Origin');

    // The Vary is not decorative: the body really does change with the header.
    expect($this->getJson('/api/provinces', ['Accept-Language' => 'en'])->json('data.40.name'))->toBe('Seville')
        ->and($this->getJson('/api/provinces', ['Accept-Language' => 'es'])->json('data.40.name'))->toBe('Sevilla');
});

it('matches partners by explicit coverage and treats national ones as everywhere', function () {
    $madrid = province(28);
    $barcelona = province(8);
    $soria = province(42);

    $notary = Partner::factory()->create(['name' => 'Notaría Madrid-Toledo']);
    $notary->provinces()->sync([$madrid->id, province(45)->id]);

    $bank = Partner::factory()->national()->create(['name' => 'Banco nacional']);
    $barcelonaOnly = Partner::factory()->create(['name' => 'Consultor Barcelona']);
    $barcelonaOnly->provinces()->sync([$barcelona->id]);

    expect(Partner::inProvince($madrid->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['Notaría Madrid-Toledo', 'Banco nacional'])
        ->and(Partner::inProvince($barcelona->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['Consultor Barcelona', 'Banco nacional'])
        // Nobody local in Soria: only the national partner is there.
        ->and(Partner::inProvince($soria->id)->pluck('name')->all())
        ->toBe(['Banco nacional']);

    expect($notary->coversProvince($madrid->id))->toBeTrue()
        ->and($notary->coversProvince($soria->id))->toBeFalse()
        ->and($bank->coversProvince($soria->id))->toBeTrue();
});

it('lets an admin set, replace and clear a partner coverage', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $madrid = province(28);
    $barcelona = province(8);

    $id = $this->actingAs($admin)->postJson('/api/admin/partners', [
        'name' => 'Estudio de arquitectura',
        'category' => 'arquitectura',
        'province_ids' => [$madrid->id, $barcelona->id],
    ])->assertCreated()->json('data.id');

    expect(Partner::find($id)->provinces->pluck('code')->all())->toBe([8, 28]);

    // Replacing the list removes what is no longer covered.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Estudio de arquitectura',
        'category' => 'arquitectura',
        'province_ids' => [$barcelona->id],
    ])->assertOk()->assertJsonPath('data.provinces.0.code', 8);

    expect(Partner::find($id)->provinces)->toHaveCount(1);

    // Switching to national wipes the explicit rows: the flag is the truth.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Estudio de arquitectura',
        'category' => 'arquitectura',
        'is_national' => true,
        'province_ids' => [$barcelona->id],
    ])->assertOk()->assertJsonPath('data.is_national', true);

    expect(Partner::find($id)->provinces)->toHaveCount(0);

    // Naming provinces again takes the partner back off national coverage, even
    // without restating the flag — the list is the intent.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Estudio de arquitectura',
        'category' => 'arquitectura',
        'province_ids' => [$madrid->id],
    ])->assertOk()->assertJsonPath('data.is_national', false);

    expect(Partner::find($id)->provinces->pluck('code')->all())->toBe([28]);

    // An unknown province is refused rather than silently dropped.
    $this->actingAs($admin)->putJson("/api/admin/partners/{$id}", [
        'name' => 'Estudio de arquitectura',
        'category' => 'arquitectura',
        'province_ids' => [999999],
    ])->assertUnprocessable()->assertJsonValidationErrors(['province_ids.0']);
});

it('filters the admin list by province and names the categories missing there', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $soria = province(42);

    $local = Partner::factory()->create(['name' => 'Notaría Soria', 'category' => 'notario']);
    $local->provinces()->sync([$soria->id]);
    $bank = Partner::factory()->national()->create(['name' => 'Banco', 'category' => 'banco']);
    $elsewhere = Partner::factory()->create(['name' => 'Notaría Barcelona', 'category' => 'notario']);
    $elsewhere->provinces()->sync([province(8)->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/partners?province_id={$soria->id}")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toEqualCanonicalizing(['Notaría Soria', 'Banco'])
        // notario and banco are covered; every other trade is a gap.
        ->and($response->json('meta.gaps'))
        ->toEqualCanonicalizing(['asesor_fiscal', 'arquitectura', 'aseguradora', 'constructora', 'abogado', 'marketing', 'otro']);

    expect($bank->fresh()->is_national)->toBeTrue();

    // Without the filter there is no gap analysis to report.
    $this->actingAs($admin)->getJson('/api/admin/partners')
        ->assertOk()
        ->assertJsonPath('meta', null);
});

it('lets a learner save their province on their profile', function () {
    $user = User::factory()->create();
    $toledo = province(45);

    $this->getJson('/api/me')->assertUnauthorized();

    $this->actingAs($user)->patchJson('/api/me', ['province_id' => $toledo->id])
        ->assertOk()
        ->assertJsonPath('data.province_id', $toledo->id)
        ->assertJsonPath('data.province.name', 'Toledo');

    expect($user->fresh()->province_id)->toBe($toledo->id);

    // Clearing it is allowed; an unknown province and a role change are not.
    $this->actingAs($user)->patchJson('/api/me', ['province_id' => null])->assertOk();
    expect($user->fresh()->province_id)->toBeNull();

    $this->actingAs($user)->patchJson('/api/me', ['province_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors(['province_id']);

    $this->actingAs($user)->patchJson('/api/me', ['role' => 'admin'])->assertOk();
    expect($user->fresh()->role)->toBe('student');
});

it('serialises partner coverage to owners and never leaks it through a free preview', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $owned = Module::factory()->for($course)->create(['order' => 1]);
    $locked = Module::factory()->for($course)->create(['order' => 2]);

    $partner = Partner::factory()->create([
        'name' => 'Notaría secreta',
        'category' => 'notario',
        'phone' => '699988877',
    ]);
    $partner->provinces()->sync([province(45)->id, province(28)->id]);

    foreach ([$owned, $locked] as $module) {
        $module->partners()->attach($partner->id, ['order' => 1]);
        $module->contentBlocks()->create([
            'order' => 1,
            'type' => 'partner_slot',
            'partner_category' => 'notario',
            'body_es' => 'Notaría recomendada.',
        ]);
    }

    // The locked module still has a free-preview lesson — playable, but it
    // must not hand out the paid contacts.
    Lesson::factory()->for($locked)->create(['order' => 1, 'is_free_preview' => true]);
    $previewBlockLesson = Lesson::factory()->for($locked)->create(['order' => 2, 'is_free_preview' => true]);
    $previewBlockLesson->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notario',
        'body_es' => 'Notaría recomendada.',
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

    expect($ownedPayload['partners'][0]['provinces'])->toHaveCount(2)
        ->and($ownedPayload['partners'][0]['provinces'][0]['code'])->toBe(28)
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
        ->and($lockedJson)->not->toContain('699988877')
        ->and($lockedJson)->not->toContain('Notaría secreta');
});

it('teases the partner count on a locked slot instead of hiding it entirely', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $locked = Module::factory()->for($course)->create(['order' => 1]);
    // A second, owned module: the learner is enrolled in the course but not in
    // the module holding the slot.
    $owned = Module::factory()->for($course)->create(['order' => 2]);

    $partner = Partner::factory()->create(['category' => 'notario', 'phone' => '611122233']);
    $locked->partners()->attach($partner->id, ['order' => 1]);
    $locked->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notario',
        'body_es' => 'Notaría recomendada.',
    ]);
    $preview = Lesson::factory()->for($locked)->create(['order' => 1, 'is_free_preview' => true]);
    $preview->contentBlocks()->create([
        'order' => 1,
        'type' => 'partner_slot',
        'partner_category' => 'notario',
        'body_es' => 'Notaría recomendada.',
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
        ->and(json_encode($module))->not->toContain('611122233');
});

it('rejects malformed partner list filters instead of failing with a 500', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin/partners?search[]=x')
        ->assertUnprocessable()->assertJsonValidationErrors(['search']);

    $this->actingAs($admin)->getJson('/api/admin/partners?province_id=999999')
        ->assertUnprocessable()->assertJsonValidationErrors(['province_id']);

    // A literal % must stay a literal, not a wildcard matching everything.
    Partner::factory()->create(['name' => 'Despacho Normal']);
    $this->actingAs($admin)->getJson('/api/admin/partners?search=%')
        ->assertOk()->assertJsonCount(0, 'data');
});
