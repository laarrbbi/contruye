<?php

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Province;
use App\Models\User;

it('files a company under the category of its activity code', function () {
    expect(Company::categoryFor('4120'))->toBe('constructoras')
        ->and(Company::categoryFor('4211'))->toBe('obra_civil')
        ->and(Company::categoryFor('7111'))->toBe('arquitectura')
        ->and(Company::categoryFor('6820'))->toBe('otros')
        ->and(Company::categoryFor(null))->toBe('otros')
        ->and(Company::categoriesForPhase(1))->toContain('agencias_inmobiliarias', 'abogados')
        ->and(Company::categoriesForPhase(6))->toContain('constructoras', 'materiales')->not->toContain('abogados');
});

it('imports an Orbis export, and a newer one updates without touching the admin\'s choice', function () {
    Province::query()->firstOrCreate(['code' => 28], ['name_es' => 'Madrid']);
    $file = orbisFile([
        ['ACS, ACTIVIDADES DE CONSTRUCCION Y SERVICIOS, SA', '4120', '28036', 'MADRID', '+34 91 343 92 00', "www.grupoacs.com\nwww.acs.es", 'ESA28004885', 'A28004885', '148987'],
        ['ESTUDIO DE ARQUITECTURA SL', '7111', '28001', 'MADRID', '', '', 'ESB00000001', 'B00000001', ''],
    ]);
    $this->artisan('directory:import', ['files' => [$file]])->assertSuccessful();

    $acs = Company::where('source_ref', 'ESA28004885')->firstOrFail();
    expect(Company::count())->toBe(2)
        ->and($acs->category)->toBe('constructoras')
        ->and($acs->city)->toBe('Madrid')
        ->and($acs->website)->toBe('https://www.grupoacs.com')
        ->and($acs->employees)->toBe(148987)
        ->and($acs->province->code)->toBe(28)
        ->and(Company::where('source_ref', 'ESB00000001')->value('category'))->toBe('arquitectura');

    $acs->update(['is_visible' => false]);
    $this->artisan('directory:import', ['files' => [$file]])->assertSuccessful();
    expect(Company::count())->toBe(2)->and($acs->fresh()->is_visible)->toBeFalse();
});

it('gives a module\'s companies only to students who own the module, and only visible ones', function () {
    $course = directoryCourse();
    [$solar, $obra] = $course->modules()->orderBy('order')->get();
    company(['name' => 'Constructora Visible']);
    company(['name' => 'Constructora Oculta', 'is_visible' => false]);
    company(['name' => 'Agencia Solar', 'category' => 'agencias_inmobiliarias']);

    // A guest gets nothing.
    $this->getJson("/api/courses/{$course->slug}/modules/{$obra->id}/companies")->assertUnauthorized();

    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $obra->id]);

    $names = collect($this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$obra->id}/companies")
        ->assertOk()->json('data'))->pluck('name');
    expect($names->all())->toBe(['Constructora Visible']);

    // Module 1 is not owned: no contacts.
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$solar->id}/companies")->assertForbidden();
});

it('lists the Partners page from the owned modules, naming the locked ones', function () {
    $course = directoryCourse();
    [$solar, $obra] = $course->modules()->orderBy('order')->get();
    company(['name' => 'Constructora Uno']);
    company(['name' => 'Agencia Solar', 'category' => 'agencias_inmobiliarias']);

    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $obra->id]);

    $response = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/companies")->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Constructora Uno'])
        ->and(collect($response->json('phases'))->firstWhere('phase', 1)['is_locked'])->toBeTrue()
        ->and(collect($response->json('phases'))->firstWhere('phase', 6)['is_locked'])->toBeFalse();

    // Not enrolled at all: nothing.
    $this->actingAs(User::factory()->create())->getJson("/api/courses/{$course->slug}/companies")->assertForbidden();
});

it('lists larger firms first, unknown sizes last, and counts the pages from the filter', function () {
    $course = directoryCourse();
    $obra = $course->modules()->orderBy('order')->get()[1];
    company(['name' => 'Sin Datos', 'employees' => null]);
    company(['name' => 'Pequeña', 'employees' => 3]);
    company(['name' => 'Grande', 'employees' => 900]);
    company(['name' => 'Aseguradora', 'category' => 'seguros', 'employees' => 50]);
    foreach (range(1, 24) as $n) {
        company(['name' => sprintf('Obra Civil %02d', $n), 'category' => 'obra_civil', 'employees' => 10]);
    }

    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $obra->id]);
    $url = "/api/courses/{$course->slug}/modules/{$obra->id}/companies";

    $all = $this->actingAs($student)->getJson($url)->assertOk();
    expect($all->json('data.0.name'))->toBe('Grande')
        ->and($all->json('meta.total'))->toBe(28)
        ->and($all->json('meta.last_page'))->toBe(2);
    $last = $this->actingAs($student)->getJson("{$url}?page=2")->assertOk();
    expect(collect($last->json('data'))->pluck('name')->last())->toBe('Sin Datos');

    $builders = $this->actingAs($student)->getJson("{$url}?category=constructoras")->assertOk();
    expect(collect($builders->json('data'))->pluck('name')->all())->toBe(['Grande', 'Pequeña', 'Sin Datos'])
        ->and($builders->json('meta.total'))->toBe(3)
        ->and($builders->json('meta.last_page'))->toBe(1);
});

it('lets the admin show and hide companies, one by one or by filter', function () {
    $admin = User::factory()->admin()->create();
    $a = company(['name' => 'Alfa Construcciones']);
    company(['name' => 'Beta Construcciones']);
    company(['name' => 'Gamma Abogados', 'category' => 'abogados']);

    // The player's per-module count is kept between requests: every change must reach it.
    expect(Company::visibleCountForPhase(6))->toBe(2);

    $this->actingAs($admin)->patchJson("/api/admin/companies/{$a->id}", ['is_visible' => false])->assertOk();
    expect($a->fresh()->is_visible)->toBeFalse()
        ->and(Company::visibleCountForPhase(6))->toBe(1);

    $this->actingAs($admin)->postJson('/api/admin/companies/visibility', ['is_visible' => false, 'category' => 'constructoras'])
        ->assertOk()->assertJsonPath('data.updated', 1);
    expect(Company::where('category', 'constructoras')->where('is_visible', true)->count())->toBe(0)
        ->and(Company::where('category', 'abogados')->value('is_visible'))->toBeTrue()
        ->and(Company::visibleCountForPhase(6))->toBe(0)
        ->and(Company::visibleCountForPhase(11))->toBe(1);

    $this->actingAs($admin)->getJson('/api/admin/companies?visible=0')->assertOk()->assertJsonPath('meta.total', 2);
    $this->actingAs(User::factory()->create())->getJson('/api/admin/companies')->assertForbidden();
});
