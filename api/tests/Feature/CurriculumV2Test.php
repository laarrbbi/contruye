<?php

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Partner;
use App\Models\User;
use App\Support\ContentValidation;
use Database\Seeders\CurriculumV2Seeder;

beforeEach(function () {
    $this->seed(CurriculumV2Seeder::class);
    $this->course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
});

it('publishes the 13 modules of the v2.0 programme in order', function () {
    expect($this->course->status)->toBe('published');

    $modules = $this->course->modules()->orderBy('order')->get();

    expect($modules)->toHaveCount(13)
        ->and($modules->pluck('order')->all())->toBe(range(1, 13));

    // Titles come straight from the source programme.
    expect($modules[0]->title_es)->toBe('Le métier, le marché et le business model')
        ->and($modules[5]->title_es)->toBe('Installer et ouvrir le chantier')
        ->and($modules[9]->title_es)->toBe('Raccordements aux réseaux et réception des VRD')
        ->and($modules[12]->title_es)->toBe('Gestion des risques et des crises');

    // Every module carries teaching content.
    foreach ($modules as $module) {
        expect($module->lessons()->count())->toBeGreaterThan(0);
    }
});

it('keeps the five VSP payment tranches verbatim in module 8', function () {
    $module = $this->course->modules()->where('order', 8)->firstOrFail();
    $text = $module->lessons()->get()
        ->flatMap(fn (Lesson $lesson) => $lesson->key_points_es ?? [])
        ->implode(' ');

    // Décret 13-431 — 20/15/35/25/5. These figures must never be reworded.
    expect($text)->toContain('20 % du prix à la signature')
        ->and($text)->toContain("15 % à l'achèvement des fondations")
        ->and($text)->toContain("35 % à l'achèvement des gros œuvres")
        ->and($text)->toContain("25 % à l'achèvement en tous corps d'état")
        ->and($text)->toContain('solde de 5 % au procès-verbal de prise de possession');

    // The caps that frame the échéancier.
    expect($text)->toContain('plafonnée à 10 % du prix de vente')
        ->and($text)->toContain('plafonnée à 20 % du prix initial');
});

it('never shows invented data in a partner slot', function () {
    $slots = ContentBlock::query()->where('type', 'partner_slot')->get();

    expect($slots)->not->toBeEmpty();

    // A slot describes the KIND of provider needed; it never carries contacts.
    foreach ($slots as $slot) {
        expect($slot->body_es)->not->toMatch('/\+?\d[\d\s.-]{8,}/')          // no phone numbers
            ->and($slot->body_es)->not->toMatch('/[\w.+-]+@[\w-]+\.[\w.]+/')  // no e-mail addresses
            ->and($slot->body_es)->not->toMatch('#https?://#');               // no URLs
    }

    // In the player, a slot with no attached partner exposes an empty list —
    // the UI renders "bientôt disponible" rather than inventing a contact.
    $module = $this->course->modules()->where('order', 2)->firstOrFail();
    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $this->course->id,
        'module_id' => null,
    ]);

    $payload = collect(
        $this->actingAs($student)->getJson("/api/courses/{$this->course->slug}/learn")->assertOk()->json('data.modules')
    )->firstWhere('id', $module->id);

    $partnerSlots = collect($payload['blocks'])->where('type', 'partner_slot');
    expect($partnerSlots)->not->toBeEmpty();
    foreach ($partnerSlots as $slot) {
        expect($slot['partners'])->toBe([]);
    }

    // Attach a real partner → the same slot now carries genuine contacts.
    $notary = Partner::factory()->create(['category' => 'notario', 'name' => 'Étude Réelle']);
    $module->partners()->attach($notary->id, ['order' => 1]);

    $refreshed = collect(
        $this->actingAs($student)->getJson("/api/courses/{$this->course->slug}/learn")->json('data.modules')
    )->firstWhere('id', $module->id);

    $filled = collect($refreshed['blocks'])->firstWhere('partner_category', 'notario');
    expect($filled['partners'])->toHaveCount(1)
        ->and($filled['partners'][0]['name'])->toBe('Étude Réelle');
});

it('serves key points and tagged blocks to students, without the A VALIDER badge', function () {
    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $this->course->id,
        'module_id' => null,
    ]);

    $modules = collect(
        $this->actingAs($student)->getJson("/api/courses/{$this->course->slug}/learn")->assertOk()->json('data.modules')
    );

    $module1 = $modules->firstWhere('order', 1);
    expect($module1['description'])->not->toBeNull()
        ->and($module1['lessons'][0]['key_points'])->not->toBeEmpty()
        // [BASE LEGALE] is exposed as a typed block.
        ->and(collect($module1['blocks'])->pluck('type'))->toContain('legal');

    // The editorial "needs validation" state is never leaked to students.
    expect(json_encode($modules->all()))->not->toContain('needs_validation');
});

it('flags the passages awaiting expert validation without deleting them', function () {
    // The marker helper drives both the admin badge and the review report.
    expect(ContentValidation::flags('montant en vigueur à vérifier auprès de la CASNOS'))->toBeTrue()
        ->and(ContentValidation::flags('module à verrouiller avec le notaire'))->toBeTrue()
        ->and(ContentValidation::flags('à confirmer localement au guichet unique'))->toBeTrue()
        ->and(ContentValidation::flags('Le promoteur dépose son dossier.'))->toBeFalse();

    // The known flagged passages are still present in the curriculum.
    $allPoints = Lesson::all()->flatMap(fn (Lesson $l) => $l->key_points_es ?? [])->implode(' ');
    expect($allPoints)->toContain('à vérifier auprès de la CASNOS')
        ->and($allPoints)->toContain('module à verrouiller avec le notaire');

    expect(ContentValidation::pending(Lesson::all()->flatMap(fn (Lesson $l) => $l->key_points_es ?? [])->all()))
        ->not->toBeEmpty();
});

it('offers the module 13.2 reflex sheet as a downloadable PDF to owners only', function () {
    $lesson = Lesson::query()->where('is_reflex_sheet', true)->firstOrFail();

    expect($lesson->title_es)->toContain('Fiche réflexe')
        ->and($lesson->module->order)->toBe(13);

    $student = User::factory()->create();

    // Not enrolled → refused.
    $this->actingAs($student)->get("/api/lessons/{$lesson->id}/reflex-sheet")->assertForbidden();

    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $this->course->id,
        'module_id' => null,
    ]);

    $response = $this->actingAs($student)->get("/api/lessons/{$lesson->id}/reflex-sheet")->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('leaves the Arabic translation pending but never blank on screen', function () {
    $module = Module::query()->where('order', 1)->firstOrFail();

    // Keys exist and are empty — the human translation pass has not run.
    expect($module->title_en)->toBeNull();

    // …but the UI still shows the French text instead of an empty string.
    app()->setLocale('ar');
    expect($module->localizedTitle())->toBe($module->title_es)
        ->and($module->localizedDescription())->toBe($module->description_es);
    app()->setLocale('fr');
});
