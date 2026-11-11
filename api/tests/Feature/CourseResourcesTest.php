<?php

use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Models\User;
use Database\Seeders\ProvinceSeeder;

/**
 * The course documents, the province directory and the package downloads
 * follow the same paywall as the lessons: a module's rows for its owners,
 * the whole annex, the PDF and the directory spreadsheet for full access,
 * the financial template's files for the owners of Financials.
 */
function resourcesCourse(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    $phases = Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id]);
    $financials = Module::factory()->create(['course_id' => $course->id, 'order' => 3, 'kind' => 'financials']);

    return [$course, $phases[0], $phases[1], $financials];
}

function owner(Course $course, ?Module $module): User
{
    $user = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'module_id' => $module?->id]);

    return $user;
}

it('serves a document to students of the course, and a full-access one to full access only', function () {
    [$course, $phase1] = resourcesCourse();
    CourseDocument::query()->create(['course_id' => $course->id, 'slug' => 'introduccion', 'order' => 1, 'title_es' => 'Introducción', 'body_es' => "# CONSTRUYE\n\n| a | b |\n|---|---|\n| 1 | 2 |", 'access' => 'enrolled']);
    CourseDocument::query()->create(['course_id' => $course->id, 'slug' => 'anexo-a', 'order' => 2, 'title_es' => 'Anexo A', 'body_es' => 'Directorio', 'access' => 'full']);

    $partial = owner($course, $phase1);

    $this->actingAs($partial)->getJson("/api/courses/{$course->slug}/documents/introduccion")
        ->assertOk()->assertJsonPath('data.title', 'Introducción')
        ->assertJsonPath('data.body_html', "<h1>CONSTRUYE</h1>\n<table>\n<thead>\n<tr>\n<th>a</th>\n<th>b</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>1</td>\n<td>2</td>\n</tr>\n</tbody>\n</table>");
    $this->actingAs($partial)->getJson("/api/courses/{$course->slug}/documents/anexo-a")->assertForbidden();
    $this->actingAs(owner($course, null))->getJson("/api/courses/{$course->slug}/documents/anexo-a")->assertOk();

    // Not a student of the course at all.
    $this->actingAs(User::factory()->create())->getJson("/api/courses/{$course->slug}/documents/introduccion")->assertForbidden();
});

it('gives a province\'s rows for the phases a student owns, and counts the others', function () {
    $this->seed(ProvinceSeeder::class);
    [$course, $phase1] = resourcesCourse();
    $madrid = Province::query()->where('code', 28)->sole();
    ProvinceGuide::query()->create([
        'province_id' => $madrid->id,
        'ccaa' => 'Comunidad de Madrid',
        'section_es' => "### Madrid · capital: Madrid\n\n- **ITP:** 6 %",
        'data' => [
            'prov' => 'Madrid', 'cap' => 'Madrid', 'ccaa' => 'Comunidad de Madrid', 'ley' => 'Ley 9/2001', 'ocup' => 'Declaración responsable',
            'cedula' => null, 'itp' => '6 %', 'ajd' => '0,75 %', 'iva' => 'IVA 10 %', 'foral' => null,
            'phases' => ['1' => [['Registro de la Propiedad', 'Nota simple']], '2' => [['Sociedad de tasación', 'Tasación'], ['Banco', 'Financiación']]],
        ],
    ]);

    $partial = owner($course, $phase1);
    $data = $this->actingAs($partial)->getJson("/api/courses/{$course->slug}/directory/{$madrid->id}")->assertOk()->json('data');

    expect($data['itp'])->toBe('6 %')
        ->and($data['phases'][0])->toMatchArray(['phase' => 1, 'is_locked' => false, 'rows' => [['name' => 'Registro de la Propiedad', 'purpose' => 'Nota simple', 'purpose_html' => 'Nota simple']]])
        ->and($data['phases'][1])->toMatchArray(['phase' => 2, 'is_locked' => true, 'rows_count' => 2, 'rows' => []])
        // The annex's full section is part of the whole course.
        ->and($data['section_html'])->toBeNull()
        ->and(json_encode($data))->not->toContain('Sociedad de tasación');

    $full = $this->actingAs(owner($course, null))->getJson("/api/courses/{$course->slug}/directory/{$madrid->id}")->json('data');
    expect($full['phases'][1]['rows'])->toHaveCount(2)
        ->and($full['section_html'])->toContain('<strong><span class="term" tabindex="0">ITP<span class="term-i"');

    $this->actingAs(User::factory()->create())->getJson("/api/courses/{$course->slug}/directory/{$madrid->id}")->assertForbidden();
});

it('hands out the package files behind their own gate, from the package as delivered', function () {
    [$course, $phase1, , $financials] = resourcesCourse();

    $partial = owner($course, $phase1);
    $this->actingAs($partial)->get("/api/courses/{$course->slug}/files/plantilla-simplificada")->assertForbidden();
    $this->actingAs($partial)->get("/api/courses/{$course->slug}/files/curso-pdf")->assertForbidden();

    // The simplified template is the extra's: its buyers have it, as delivered.
    $financialsOwner = owner($course, $financials);
    $response = $this->actingAs($financialsOwner)->get("/api/courses/{$course->slug}/files/plantilla-simplificada")->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('Plantilla_simplificada_corregida.xlsx')
        ->and(md5_file($response->getFile()->getPathname()))->toBe(md5_file(base_path('../curso/2_plantilla_financiera/excel_corregido/Plantilla_simplificada_corregida.xlsx')));
    $this->actingAs($financialsOwner)->get("/api/courses/{$course->slug}/files/curso-pdf")->assertForbidden();

    // The whole course is not the extra.
    $full = owner($course, null);
    $this->actingAs($full)->get("/api/courses/{$course->slug}/files/plantilla-simplificada")->assertForbidden();
    $this->actingAs($full)->get("/api/courses/{$course->slug}/files/curso-pdf")->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->actingAs($full)->get("/api/courses/{$course->slug}/files/directorio-partners")->assertOk();
    $this->actingAs($full)->get("/api/courses/{$course->slug}/files/no-existe")->assertNotFound();
});

it('hands out the instructor\'s templates of a module to its owners, as delivered, and says which are locked', function () {
    [$course, $phase1, $phase2] = resourcesCourse();
    $url = fn (string $key) => "/api/courses/{$course->slug}/files/{$key}";

    // Module 1's owner has both, byte for byte the files in materiales/.
    $first = owner($course, $phase1);
    foreach (['esquema-bbdd-suelo' => 'Esquema_BBDD_Gestion_de_Suelo.xlsx', 'calculo-precio-solar' => 'Plantilla_calculo_precio_solar.xlsx'] as $key => $name) {
        $response = $this->actingAs($first)->get($url($key))->assertOk();
        expect($response->headers->get('content-disposition'))->toContain($name)
            ->and(hash_file('sha256', $response->getFile()->getPathname()))->toBe(hash_file('sha256', base_path("../materiales/modulo_01/{$name}")));
    }

    // Another module's owner does not, and sees them locked with their module.
    $second = owner($course, $phase2);
    $this->actingAs($second)->get($url('calculo-precio-solar'))->assertForbidden();
    $files = collect($this->actingAs($second)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.course.files'))->keyBy('key');
    expect($files['calculo-precio-solar'])->toMatchArray(['access' => 'module', 'module' => 1, 'is_locked' => true]);

    $files = collect($this->actingAs($first)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.course.files'))->keyBy('key');
    expect($files['esquema-bbdd-suelo']['is_locked'])->toBeFalse()
        ->and($files['curso-pdf']['is_locked'])->toBeTrue();

    // The whole course includes module 1.
    $this->actingAs(owner($course, null))->get($url('esquema-bbdd-suelo'))->assertOk();
});

it('gives the professional\'s original feasibility guide to buyers of the extra only, as delivered', function () {
    [$course, , $phase2, $financials] = resourcesCourse();
    $url = "/api/courses/{$course->slug}/files/guia-analisis-viabilidad";

    $buyer = owner($course, $financials);
    $response = $this->actingAs($buyer)->get($url)->assertOk();
    expect(hash_file('sha256', $response->getFile()->getPathname()))
        ->toBe(hash_file('sha256', base_path('../materiales/financials/Plantilla_Guia_Analisis_de_Viabilidad.xlsx')));

    // It is the extra's model: not with Module 2, nor with the whole course.
    $this->actingAs(owner($course, $phase2))->get($url)->assertForbidden();
    $this->actingAs(owner($course, null))->get($url)->assertForbidden();
});
