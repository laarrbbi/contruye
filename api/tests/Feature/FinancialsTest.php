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
 * Financials: the API checks who is asking and passes the inputs to the
 * financials service, which runs the professional's template. The service
 * itself is tested in financials/test; here it is faked at the HTTP boundary.
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

it('is for the owners of the Financials module', function () {
    Http::fake();
    [$course, $phase, $financials] = financialsCourse();

    $other = enrolledIn($course, $phase);
    $this->actingAs($other)->getJson("/api/courses/{$course->slug}/financials/inputs")->assertForbidden();
    $this->actingAs($other)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => []])->assertForbidden();
    $this->actingAs($other)->getJson("/api/courses/{$course->slug}/financials/scenarios")->assertForbidden();
    Http::assertNothingSent();

    // The template's own dictionary of inputs, as delivered.
    $inputs = $this->actingAs(enrolledIn($course, $financials))
        ->getJson("/api/courses/{$course->slug}/financials/inputs")->assertOk()->json('data');
    expect($inputs['entradas'])->toHaveCount(275)
        ->and($inputs['entradas'][2])->toMatchArray(['hoja' => 'Datos de Partida', 'celda' => 'C9', 'tipo' => 'numero'])
        ->and($inputs['ejemplos'][0]['datos']['Datos de Partida']['C9'])->toBe(24);
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

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/calcular')
        && json_decode($request->body(), true) === [
            'datos' => ['Datos de Partida' => ['C9' => 24, 'D79' => '01/01/2027', 'D80' => 'SI', 'D81' => 0.04]],
            'hojas' => ['8º Resumen'],
        ]);
});

it('refuses inputs outside the template before they reach it, and relays the template\'s own refusal', function () {
    Http::fake(['*/calcular' => Http::response(['error' => 'La celda Datos de Partida!A1 no es una entrada de la plantilla'], 422)]);
    [$course, , $financials] = financialsCourse();
    $student = enrolledIn($course, $financials);

    // A sheet that has no input cells, or a value that is not a value.
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => ['3ª Coste Total Promoción' => ['C24' => 1]]])
        ->assertUnprocessable()->assertJsonValidationErrors('datos');
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => ['Datos de Partida' => ['C9' => ['x']]]])
        ->assertUnprocessable();
    Http::assertNothingSent();

    // A cell of an input sheet that is a formula: the service says no.
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => ['Datos de Partida' => ['A1' => 'x']]])
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
