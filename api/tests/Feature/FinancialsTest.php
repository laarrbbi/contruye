<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FinancialScenario;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Financials: the professional template, the extra at the end of the course.
 * The API checks who is asking, holds the inputs to the template's dictionary
 * and passes them, with the legal parameters in force, to the financials
 * service, which runs the template. The service itself is tested in
 * financials/test; here it is faked at the HTTP boundary.
 */
function financialsCourse(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    $phase = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $financials = Module::factory()->create(['course_id' => $course->id, 'order' => 2, 'kind' => 'financials']);

    return [$course, $phase, $financials];
}

function enrolledIn(Course $course, ?Module $module): User
{
    $user = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'module_id' => $module?->id]);

    return $user;
}

it('is for the buyers of the extra: not even the whole course opens it', function () {
    Http::fake();
    [$course, $phase, $financials] = financialsCourse();

    foreach ([enrolledIn($course, $phase), enrolledIn($course, null)] as $other) {
        $this->actingAs($other)->getJson("/api/courses/{$course->slug}/financials/inputs")->assertForbidden();
        $this->actingAs($other)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => []])->assertForbidden();
        $this->actingAs($other)->postJson("/api/courses/{$course->slug}/financials/excel", ['datos' => []])->assertForbidden();
        $this->actingAs($other)->getJson("/api/courses/{$course->slug}/financials/scenarios")->assertForbidden();
    }
    Http::assertNothingSent();

    // The template's own dictionary of inputs, as delivered, and the legal parameters it applies.
    $inputs = $this->actingAs(enrolledIn($course, $financials))
        ->getJson("/api/courses/{$course->slug}/financials/inputs")->assertOk()->json('data');
    expect($inputs['entradas'])->toHaveCount(275)
        ->and($inputs['entradas'][2])->toMatchArray(['hoja' => 'Datos de Partida', 'celda' => 'C9', 'tipo' => 'numero'])
        ->and($inputs['ejemplos'][0]['datos']['Datos de Partida']['C9'])->toBe(24)
        ->and($inputs['legal']['parameters'][0])->toMatchArray(['code' => 'IVA_GENERAL', 'value' => 0.21])
        ->and($inputs['legal']['regions'])->toHaveCount(19);
});

it('passes the inputs to the template service and returns its result untouched', function () {
    Http::fake(['*/calcular' => Http::response([
        'calculo' => ['iteraciones' => 22, 'cambio_maximo' => 0.0001, 'convergido' => true],
        'resumen' => [['concepto' => 'TOTAL', 'neto' => 6056013.13]],
        'vista' => [],
        'hojas' => [],
    ])]);
    [$course, , $financials] = financialsCourse();

    $this->actingAs(enrolledIn($course, $financials))->postJson("/api/courses/{$course->slug}/financials/calculate", [
        'datos' => ['Datos de Partida' => ['C9' => 24, 'D79' => '01/01/2027', 'D80' => 'SI', 'D81' => 0.04]],
        'hojas' => ['8º Resumen'],
    ])->assertOk()->assertJsonPath('data.resumen.0.neto', 6056013.13);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return str_ends_with($request->url(), '/calcular')
            && $body['datos'] === ['Datos de Partida' => ['C9' => 24, 'D79' => '01/01/2027', 'D80' => 'SI', 'D81' => 0.04]]
            && $body['hojas'] === ['8º Resumen']
            // The legal parameters in force go with every calculation.
            && $body['parametros']['IVA_GENERAL'] === 0.21
            && $body['parametros']['comunidades']['Cataluña'] === ['itp' => 0.1, 'ajd' => 0.015];
    });
});

it('refuses inputs outside the template before they reach it, and relays the template\'s own refusal', function () {
    Http::fake(['*/calcular' => Http::response(['error' => 'La celda Datos de Partida!A1 no es una entrada de la plantilla'], 422)]);
    [$course, , $financials] = financialsCourse();
    $student = enrolledIn($course, $financials);

    // A sheet that has no input cells, a cell that is a formula, a legal
    // parameter, a value that is not a value, a community that is not one.
    foreach ([
        ['3ª Coste Total Promoción' => ['C24' => 1]],
        ['Datos de Partida' => ['A1' => 'x']],
        ['Parámetros legales' => ['C5' => 0.5]],
        ['Datos de Partida' => ['C9' => ['x']]],
        ['Datos de Partida' => ['C9' => '24']],
        ['Datos de Partida' => ['D93' => 'Narnia']],
        ['Datos de Partida' => ['D80' => 'sí']],
    ] as $datos) {
        $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => $datos])
            ->assertUnprocessable()->assertJsonValidationErrors('datos');
    }
    Http::assertNothingSent();

    // What the template itself refuses comes back as it says it.
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => ['Datos de Partida' => ['C9' => 24]]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.datos.0', 'La celda Datos de Partida!A1 no es una entrada de la plantilla');
});

it('says the calculator is unavailable instead of failing when the service is down', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    [$course, , $financials] = financialsCourse();

    $this->actingAs(enrolledIn($course, $financials))
        ->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => []])
        ->assertStatus(503)
        ->assertJsonPath('message', __('The financial template is not available at the moment. Please try again later.'));
});

it('keeps each student\'s scenarios to themselves', function () {
    [$course, , $financials] = financialsCourse();
    $alice = enrolledIn($course, $financials);
    $bob = enrolledIn($course, $financials);

    $id = $this->actingAs($alice)->postJson("/api/courses/{$course->slug}/financials/scenarios", [
        'name' => '24 viviendas en Sevilla',
        'inputs' => ['Datos de Partida' => ['C9' => 24]],
    ])->assertCreated()->json('data.id');

    $this->actingAs($alice)->putJson("/api/courses/{$course->slug}/financials/scenarios/{$id}", [
        'name' => '24 viviendas en Sevilla (v2)',
        'inputs' => ['Datos de Partida' => ['C9' => 26]],
    ])->assertOk()->assertJsonPath('data.inputs.Datos de Partida.C9', 26);

    expect($this->actingAs($bob)->getJson("/api/courses/{$course->slug}/financials/scenarios")->json('data'))->toBe([]);
    $this->actingAs($bob)->putJson("/api/courses/{$course->slug}/financials/scenarios/{$id}", ['name' => 'x', 'inputs' => []])->assertNotFound();
    $this->actingAs($bob)->deleteJson("/api/courses/{$course->slug}/financials/scenarios/{$id}")->assertNotFound();

    $this->actingAs($alice)->deleteJson("/api/courses/{$course->slug}/financials/scenarios/{$id}")->assertOk();
    expect(FinancialScenario::query()->count())->toBe(0);
});

it('sends an empty set of inputs as an object, the shape the service expects', function () {
    Http::fake(['*/calcular' => Http::response(['calculo' => [], 'resumen' => [], 'vista' => [], 'hojas' => []])]);
    [$course, , $financials] = financialsCourse();

    $this->actingAs(enrolledIn($course, $financials))
        ->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => []])->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->body(), '"datos":{}'));
});
