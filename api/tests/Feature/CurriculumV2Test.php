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

it('publishes the 12 phases of the instructor\'s outline in order', function () {
    expect($this->course->status)->toBe('published');

    $modules = $this->course->modules()->orderBy('order')->get();

    expect($modules)->toHaveCount(12)
        ->and($modules->pluck('order')->all())->toBe(range(1, 12));

    // Titles come straight from the instructor's outline of the twelve phases.
    expect($modules[0]->title_es)->toBe('Solar: búsqueda, análisis urbanístico, due diligence y adquisición')
        ->and($modules[3]->title_es)->toBe('Licencias: licencia de obras, autorizaciones, requisitos administrativos y otorgamiento')
        ->and($modules[5]->title_es)->toBe('Construcción: cimentación, estructura, cerramientos, instalaciones y acabados')
        ->and($modules[11]->title_es)->toBe('Postventa y cierre: información de la Comunidad de Propietarios, garantías, incidencias y liquidación de la promoción');

    // Every module carries teaching content.
    foreach ($modules as $module) {
        expect($module->lessons()->count())->toBeGreaterThan(0);
    }
});

it('keeps the legal guarantees of buyers\' money verbatim in phases 5 and 8', function () {
    $text = fn (int $phase) => $this->course->modules()->where('order', $phase)->firstOrFail()
        ->lessons()->get()
        ->flatMap(fn (Lesson $lesson) => $lesson->key_points_es ?? [])
        ->implode(' ');

    // Disposición adicional primera LOE: these terms must never be reworded.
    expect($text(5))->toContain('su devolución más el interés legal mediante seguro de caución o aval solidario de una entidad de crédito')
        ->and($text(5))->toContain('en una cuenta especial separada de cualquier otro fondo')
        ->and($text(5))->toContain('Cada comprador recibe su certificado individual.')
        // Art. 19 LOE: ten years, 100 % of the final cost of the works.
        ->and($text(5))->toContain('durante diez años los daños estructurales por el 100 % del coste final de la obra');

    // The sale contract refers back to the same guarantee, and the VAT on each payment.
    expect($text(8))->toContain('garantía de las cantidades a cuenta con entrega del certificado individual')
        ->and($text(8))->toContain('IVA del 10 % sobre cada cantidad a cuenta');
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
    // the UI renders "próximamente" rather than inventing a contact.
    $module = $this->course->modules()->where('order', 1)->firstOrFail();
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
    $notary = Partner::factory()->create(['category' => 'notario', 'name' => 'Notaría Real']);
    $module->partners()->attach($notary->id, ['order' => 1]);

    $refreshed = collect(
        $this->actingAs($student)->getJson("/api/courses/{$this->course->slug}/learn")->json('data.modules')
    )->firstWhere('id', $module->id);

    $filled = collect($refreshed['blocks'])->firstWhere('partner_category', 'notario');
    expect($filled['partners'])->toHaveCount(1)
        ->and($filled['partners'][0]['name'])->toBe('Notaría Real');
});

it('serves key points and tagged blocks to students, without the A VALIDAR badge', function () {
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
        // The legal references are exposed as a typed block.
        ->and(collect($module1['blocks'])->pluck('type'))->toContain('legal');

    // The editorial "needs validation" state is never leaked to students.
    expect(json_encode($modules->all()))->not->toContain('needs_validation');
});

it('flags the passages awaiting expert validation without deleting them', function () {
    // The marker helper drives both the admin badge and the review report.
    expect(ContentValidation::flags('Plazo habitual (pendiente de validar con el formador).'))->toBeTrue()
        ->and(ContentValidation::flags('Porcentajes orientativos pendientes de validar con el formador.'))->toBeTrue()
        ->and(ContentValidation::flags('Importe vigente por confirmar con el ayuntamiento.'))->toBeTrue()
        ->and(ContentValidation::flags('El promotor solicita la licencia de obras.'))->toBeFalse();

    // The known flagged passages are still present in the curriculum.
    $allPoints = Lesson::all()->flatMap(fn (Lesson $l) => $l->key_points_es ?? [])->implode(' ');
    expect($allPoints)->toContain('pendiente de validar con el asesor fiscal')
        ->and($allPoints)->toContain('Lista definitiva pendiente de validar con el notario.');

    expect(ContentValidation::pending(Lesson::all()->flatMap(fn (Lesson $l) => $l->key_points_es ?? [])->all()))
        ->not->toBeEmpty();
});

it('offers the lesson 7.4 ficha de actuación as a downloadable PDF to owners only', function () {
    $lesson = Lesson::query()->where('is_reflex_sheet', true)->firstOrFail();

    expect($lesson->title_es)->toContain('Ficha de actuación')
        ->and($lesson->module->order)->toBe(7);

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

it('leaves the English translation pending but never blank on screen', function () {
    $module = Module::query()->where('order', 1)->firstOrFail();

    // Keys exist and are empty — the human translation pass has not run.
    expect($module->title_en)->toBeNull();

    // …but the UI still shows the Spanish text instead of an empty string.
    app()->setLocale('en');
    expect($module->localizedTitle())->toBe($module->title_es)
        ->and($module->localizedDescription())->toBe($module->description_es);
    app()->setLocale('es');
});
