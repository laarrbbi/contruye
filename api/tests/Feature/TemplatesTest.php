<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LegalParameter;
use App\Models\LegalParameterChange;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\FinancialsExtra;
use App\Services\LegalParameters;
use App\Services\ReferenceValues;
use App\Support\Financials\XlsxFiller;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * Package 4.1: five small templates included in the course, each in its
 * module; the professional template as a paid extra at the end, off until
 * the admin switches it on; the legal parameters every template applies,
 * kept by the admin with their history. The engine itself is tested in
 * financials/test and in the package (39 checks); here the service is faked.
 */

/** The course's modules 1 to 8 and Financials, as the package numbers them. */
function templatesCourse(): array
{
    $course = Course::factory()->create(['status' => 'published', 'slug' => 'promocion-inmobiliaria-espana', 'price_eur' => 490]);
    $phases = collect(range(1, 8))->mapWithKeys(fn (int $order) => [$order => Module::factory()->create(['course_id' => $course->id, 'order' => $order, 'price_eur' => 49])]);
    $financials = Module::factory()->create(['course_id' => $course->id, 'order' => 9, 'kind' => Module::KIND_FINANCIALS, 'price_eur' => null]);

    return [$course, $phases, $financials];
}

function studentOf(Course $course, ?Module $module): User
{
    $user = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'module_id' => $module?->id]);

    return $user;
}

/** A cell of a workbook as stored: [type, value or formula]. */
function workbookCell(string $path, string $sheet, string $address): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $targets = [];
    foreach ($rels->Relationship as $rel) {
        $target = ltrim((string) $rel['Target'], '/');
        $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
    }
    $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $part = null;
    foreach ($workbook->sheets->sheet as $entry) {
        if ((string) $entry['name'] === $sheet) {
            $part = $targets[(string) $entry->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']];
        }
    }
    $xml = simplexml_load_string($zip->getFromName($part));
    $zip->close();
    $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $cell = $xml->xpath("//m:c[@r='{$address}']")[0] ?? null;

    return match (true) {
        $cell === null => ['none', null],
        isset($cell->f) => ['formula', (string) $cell->f],
        (string) $cell['t'] === 'inlineStr' => ['text', (string) $cell->is->t],
        default => ['number', (float) $cell->v],
    };
}

it('starts from the package\'s legal parameters, and the engine gets them whole', function () {
    $engine = app(LegalParameters::class)->forEngine();

    expect(LegalParameter::query()->orderBy('order')->pluck('code')->all())->toBe(['IVA_GENERAL', 'IVA_REDUCIDO', 'IPS', 'CLEA', 'INTERES_LEGAL', 'AJD_PRESTAMO'])
        ->and($engine)->toMatchArray(['IVA_GENERAL' => 0.21, 'IVA_REDUCIDO' => 0.1, 'IPS' => 0.08, 'CLEA' => 0.0015, 'INTERES_LEGAL' => 0.0325, 'AJD_PRESTAMO' => 0.0])
        ->and($engine['comunidades'])->toHaveCount(19)
        ->and($engine['comunidades']['Comunitat Valenciana'])->toBe(['itp' => 0.09, 'ajd' => 0.014])
        ->and(app(LegalParameters::class)->review()['at'])->toBe('2026-11-08');
});

it('lets the admin review the legal parameters, keeps every change, and refuses a value out of range', function () {
    $admin = User::factory()->admin()->create(['name' => 'Larbi']);
    $page = $this->actingAs($admin)->getJson('/api/admin/templates')->assertOk()->json('data.legal');
    expect($page['parameters'][0])->toMatchArray(['code' => 'IVA_GENERAL', 'value' => 0.21, 'original_value' => 0.16, 'legal_basis' => 'Ley 37/1992 del IVA, art. 90'])
        ->and($page['regions'][8])->toMatchArray(['name' => 'Cataluña', 'itp' => 0.1, 'ajd' => 0.015])
        ->and($page['history'])->toBe([]);

    $percent = fn (array $legal) => [
        'parameters' => collect($legal['parameters'])->mapWithKeys(fn ($p) => [$p['code'] => $p['value'] * 100])->all(),
        'regions' => collect($legal['regions'])->mapWithKeys(fn ($r) => [$r['name'] => ['itp' => $r['itp'] * 100, 'ajd' => $r['ajd'] * 100]])->all(),
    ];
    $review = $percent($page);
    $review['parameters']['IVA_GENERAL'] = 22;
    $review['regions']['Cataluña']['ajd'] = 1.8;

    // Not confirmed, or a value of 100 % or more, or below 0: nothing is saved.
    $this->actingAs($admin)->putJson('/api/admin/templates/legal', $review)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->actingAs($admin)->putJson('/api/admin/templates/legal', ['confirmed' => true] + array_replace_recursive($review, ['parameters' => ['IVA_REDUCIDO' => 100]]))
        ->assertUnprocessable()->assertJsonValidationErrors('parameters.IVA_REDUCIDO');
    $this->actingAs($admin)->putJson('/api/admin/templates/legal', ['confirmed' => true] + array_replace_recursive($review, ['regions' => ['Galicia' => ['itp' => -1]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('regions.Galicia.itp');
    $this->actingAs($admin)->putJson('/api/admin/templates/legal', ['confirmed' => true] + array_replace_recursive($review, ['regions' => ['Narnia' => ['itp' => 1, 'ajd' => 1]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('regions');
    expect(LegalParameterChange::query()->count())->toBe(0);

    $saved = $this->actingAs($admin)->putJson('/api/admin/templates/legal', ['confirmed' => true] + $review)->assertOk()->json('data.legal');

    // Two values changed: two entries, with who, before and after; the review is dated.
    expect($saved['parameters'][0]['value'])->toBe(0.22)
        ->and($saved['regions'][8]['ajd'])->toBe(0.018)
        ->and(collect($saved['history'])->map(fn ($c) => [$c['subject'], $c['field'], $c['old_value'], $c['new_value'], $c['by']])->all())->toEqualCanonicalizing([
            ['IVA_GENERAL', 'value', 0.21, 0.22, 'Larbi'],
            ['Cataluña', 'ajd', 0.015, 0.018, 'Larbi'],
        ])
        ->and($saved['reviewed_by'])->toBe('Larbi')
        ->and(substr($saved['reviewed_at'], 0, 10))->toBe(now()->toDateString());

    // Saving the same values again records no change, only the review.
    $this->actingAs($admin)->putJson('/api/admin/templates/legal', ['confirmed' => true] + $percent($saved))->assertOk();
    expect(LegalParameterChange::query()->count())->toBe(2);

    $this->actingAs(User::factory()->create())->getJson('/api/admin/templates')->assertForbidden();
    $this->actingAs(User::factory()->create())->putJson('/api/admin/templates/legal', ['confirmed' => true] + $review)->assertForbidden();
});

it('applies a change made in the admin to the next calculation of every template', function () {
    Http::fake(['*' => Http::response(['calculo' => [], 'parametros' => [], 'hoja' => ['celdas' => []], 'resumen' => [], 'vista' => [], 'hojas' => []])]);
    [$course, $phases, $financials] = templatesCourse();
    $student = studentOf($course, null);
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $financials->id]);

    LegalParameter::query()->where('code', 'IVA_GENERAL')->update(['value' => 0.22]);

    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/1/calculate", ['datos' => []])->assertOk();
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => []])->assertOk();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => json_decode($request->body(), true)['parametros']['IVA_GENERAL'] === 0.22);
});

it('keeps the extra out of every plan: the whole course does not open Financials', function () {
    [$course, $phases, $financials] = templatesCourse();
    $full = studentOf($course, null);

    expect($full->hasFullAccessTo($course))->toBeTrue()
        ->and($full->hasAccessToModule($phases[8]))->toBeTrue()
        ->and($full->hasAccessToModule($financials))->toBeFalse()
        ->and($full->accessibleModuleIds($course)->all())->not->toContain($financials->id);

    // Every module but Financials, one by one, is the whole course too.
    $completer = User::factory()->create();
    foreach ($phases as $module) {
        Enrollment::factory()->create(['user_id' => $completer->id, 'course_id' => $course->id, 'module_id' => $module->id]);
    }
    expect($completer->hasFullAccessTo($course))->toBeTrue();

    $learn = $this->actingAs($full)->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data.course');
    expect($learn['full_access'])->toBeTrue()
        ->and($learn['total_modules_count'])->toBe(8)
        ->and($learn['owned_modules_count'])->toBe(8);

    // Each module says which small template it carries: 1, 2, 5, 7 and 8 do.
    $modules = collect($this->actingAs($full)->getJson("/api/courses/{$course->slug}/learn")->json('data.modules'))->keyBy('order');
    expect($modules->map(fn ($m) => $m['template']['number'] ?? null)->filter()->all())->toBe([1 => 1, 2 => 2, 5 => 3, 7 => 4, 8 => 5]);

    $dashboard = $this->actingAs($full)->getJson('/api/enrollments')->assertOk()->json('data.0');
    expect($dashboard['full_access'])->toBeTrue()
        ->and($dashboard['total_modules_count'])->toBe(8)
        ->and($dashboard['finished'])->toBeFalse();

    // The final exam passed, the course is finished: the dashboard offers the extra.
    $exam = Quiz::factory()->create(['course_id' => $course->id, 'kind' => 'final']);
    QuizAttempt::query()->create(['quiz_id' => $exam->id, 'user_id' => $full->id, 'answers' => [], 'score' => 36, 'total' => 40, 'passed' => true, 'started_at' => now(), 'submitted_at' => now()]);
    expect($this->actingAs($full)->getJson('/api/enrollments')->json('data.0.finished'))->toBeTrue();

    // The course's price list does not include it either.
    $quote = $this->actingAs(User::factory()->create())->postJson('/api/checkout/quote', ['course_id' => $course->id])->assertOk()->json('data');
    expect($quote['full_price_anchor'])->toBe(8 * 49);
});

it('sells the extra only once the admin switches it on with a price, to students of any plan', function () {
    config(['services.manual_payment.iban' => 'ES91 2100 0418 4502 0005 1332', 'services.manual_payment.holder' => '[DEMO] Construye Academy S.L.']);
    [$course, $phases, $financials] = templatesCourse();
    $admin = User::factory()->admin()->create();
    $student = studentOf($course, $phases[1]);
    $extra = ['course_id' => $course->id, 'scope' => 'extra'];

    // Off as delivered: no quote, no order, and the student's page says so.
    $this->actingAs($student)->postJson('/api/checkout/quote', $extra)->assertUnprocessable()->assertJsonValidationErrors('scope');
    $this->actingAs($student)->postJson('/api/checkout', $extra + ['method' => 'manual_transfer'])->assertUnprocessable();
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/extra")->assertOk()
        ->assertJsonPath('data', ['on_sale' => false, 'price_eur' => null, 'owned' => false, 'module_id' => $financials->id]);

    // Switched on without a price: refused.
    $this->actingAs($admin)->putJson('/api/admin/templates/extra', ['enabled' => true])->assertUnprocessable()->assertJsonValidationErrors('price_eur');
    $this->actingAs($admin)->putJson('/api/admin/templates/extra', ['enabled' => true, 'price_eur' => 290])->assertOk()
        ->assertJsonPath('data.extra', ['enabled' => true, 'price_eur' => 290, 'on_sale' => true, 'buyers' => 0]);

    // Not for someone who is not a student of the course.
    $this->actingAs(User::factory()->create())->postJson('/api/checkout/quote', $extra)->assertUnprocessable();

    // Its own price: no tier, no campaign, no coupon.
    $coupon = Coupon::factory()->create(['percent_off' => 50]);
    $this->actingAs($student)->postJson('/api/checkout/quote', $extra + ['coupon_code' => $coupon->code])->assertOk()
        ->assertJsonPath('data.total', 290)
        ->assertJsonPath('data.items.0.module_id', $financials->id);
    $orderId = $this->actingAs($student)->postJson('/api/checkout', $extra + ['method' => 'manual_transfer', 'coupon_code' => $coupon->code])
        ->assertCreated()->assertJsonPath('data.payment_instructions.amount', 290)->json('data.order.id');
    expect($coupon->fresh()->uses)->toBe(0);

    // Paid, it opens Financials and nothing else.
    $order = Order::query()->findOrFail($orderId);
    expect($order->scope)->toBe('modules')->and($order->markPaidAndEnroll())->toBeTrue();
    expect($student->hasAccessToModule($financials))->toBeTrue()
        ->and($student->hasAccessToModule($phases[2]))->toBeFalse()
        ->and(app(FinancialsExtra::class)->view($course, $student)['owned'])->toBeTrue();
    $this->actingAs($student)->postJson('/api/checkout/quote', $extra)->assertUnprocessable();

    // Off again: off sale, and the buyer keeps it.
    $this->actingAs($admin)->putJson('/api/admin/templates/extra', ['enabled' => false, 'price_eur' => 290])->assertOk()->assertJsonPath('data.extra.on_sale', false);
    expect($student->hasAccessToModule($financials))->toBeTrue();
});

it('gives each small template to the owners of its module, and calculates it with its own inputs only', function () {
    Http::fake(['*/pequenas/3/calcular' => Http::response(['calculo' => ['iteraciones' => 1], 'parametros' => [], 'hoja' => ['nombre' => 'Calculadora', 'celdas' => ['B22' => ['v' => 178000]]]])]);
    [$course, $phases] = templatesCourse();
    $module5 = studentOf($course, $phases[5]);

    $list = $this->actingAs($module5)->getJson("/api/courses/{$course->slug}/templates")->assertOk()->json('data');
    expect(collect($list)->map(fn ($t) => [$t['number'], $t['module_order'], $t['is_locked']])->all())->toBe([
        [1, 1, true], [2, 2, true], [3, 5, false], [4, 7, true], [5, 8, true],
    ]);

    $this->actingAs($module5)->getJson("/api/courses/{$course->slug}/templates/1")->assertForbidden();
    $this->actingAs($module5)->postJson("/api/courses/{$course->slug}/templates/1/calculate", ['datos' => []])->assertForbidden();
    $this->actingAs($module5)->postJson("/api/courses/{$course->slug}/templates/1/excel", ['datos' => []])->assertForbidden();
    $this->actingAs($module5)->getJson("/api/courses/{$course->slug}/templates/9")->assertNotFound();

    $show = $this->actingAs($module5)->getJson("/api/courses/{$course->slug}/templates/3")->assertOk()->json('data');
    expect($show['title'])->toBe('Plantilla 3 · Préstamo promotor')
        ->and($show['entradas'])->toHaveCount(9)
        ->and($show['legal']['reviewed_at'])->toBe('2026-11-08');

    // Only its input cells, each of its type.
    foreach ([['Calculadora' => ['B22' => 1]], ['Parámetros legales' => ['C5' => 0.5]], ['Calculadora' => ['B4' => 'mucho']]] as $datos) {
        $this->actingAs($module5)->postJson("/api/courses/{$course->slug}/templates/3/calculate", ['datos' => $datos])->assertUnprocessable();
    }
    Http::assertNothingSent();

    $this->actingAs($module5)->postJson("/api/courses/{$course->slug}/templates/3/calculate", ['datos' => ['Calculadora' => ['B4' => 3000000]]])
        ->assertOk()->assertJsonPath('data.hoja.celdas.B22.v', 178000);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/pequenas/3/calcular')
        && json_decode($request->body(), true)['datos'] === ['Calculadora' => ['B4' => 3000000]]);
});

it('hands out a small template in Excel with the student\'s inputs and the legal parameters in force', function () {
    [$course, $phases] = templatesCourse();
    LegalParameter::query()->where('code', 'IVA_GENERAL')->update(['value' => 0.22]);
    $student = studentOf($course, $phases[1]);

    $response = $this->actingAs($student)->post("/api/courses/{$course->slug}/templates/1/excel", [
        'datos' => ['Calculadora' => ['B4' => 'Comunidad de Madrid', 'B5' => 750000, 'B6' => 'NO']],
    ])->assertOk();
    $path = $response->getFile()->getPathname();

    expect($response->headers->get('content-disposition'))->toContain('1_compra_del_solar.xlsx')
        ->and(workbookCell($path, 'Calculadora', 'B4'))->toBe(['text', 'Comunidad de Madrid'])
        ->and(workbookCell($path, 'Calculadora', 'B5'))->toBe(['number', 750000.0])
        ->and(workbookCell($path, 'Calculadora', 'B6'))->toBe(['text', 'NO'])
        // The formulas travel untouched.
        ->and(workbookCell($path, 'Calculadora', 'B17'))->toBe(['formula', 'B5+B14+B15+B16+B9+B10'])
        // The legal parameters in force, not the package's.
        ->and(workbookCell($path, 'Parámetros legales', 'C5'))->toBe(['number', 0.22])
        ->and(workbookCell($path, 'Parámetros legales', 'B27'))->toBe(['number', 0.06]);
});

it('hands out the professional template in Excel to its buyers, with their inputs, dates as Excel dates', function () {
    [$course, $phases, $financials] = templatesCourse();
    $buyer = studentOf($course, $financials);

    $response = $this->actingAs($buyer)->post("/api/courses/{$course->slug}/financials/excel", [
        'datos' => ['Datos de Partida' => ['C9' => 24, 'D79' => '2027-01-01', 'D93' => 'Cataluña', 'D121' => 'chalets']],
    ])->assertOk();
    $path = $response->getFile()->getPathname();

    expect(workbookCell($path, 'Datos de Partida', 'C9'))->toBe(['number', 24.0])
        ->and(workbookCell($path, 'Datos de Partida', 'D79'))->toBe(['number', 46388.0])
        ->and(workbookCell($path, 'Datos de Partida', 'D93'))->toBe(['text', 'Cataluña'])
        ->and(workbookCell($path, 'Parámetros legales', 'B36')[0])->toBe('formula')
        ->and(workbookCell($path, 'Parámetros legales', 'C5'))->toBe(['number', 0.21]);
});

it('writes into a copy only, keeping each cell\'s style and every other part of the workbook', function () {
    $source = base_path('../curso/2_plantilla_financiera/plantillas_pequenas/3_prestamo_promotor.xlsx');
    $before = md5_file($source);

    $copy = XlsxFiller::fill($source, ['Calculadora' => ['B4' => 2500000.5, 'Z999' => 'nuevo']]);

    $zip = new ZipArchive;
    $zip->open($copy);
    $parts = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
    $zip->close();
    $original = new ZipArchive;
    $original->open($source);
    $originalParts = collect(range(0, $original->numFiles - 1))->map(fn ($i) => $original->getNameIndex($i))->all();
    $original->close();

    expect(md5_file($source))->toBe($before)
        ->and($parts)->toEqualCanonicalizing($originalParts)
        ->and(workbookCell($copy, 'Calculadora', 'B4'))->toBe(['number', 2500000.5])
        ->and(workbookCell($copy, 'Calculadora', 'Z999'))->toBe(['text', 'nuevo']);
    unlink($copy);
});

it('calculates through the engine run as a process where no service can stay up (the Vercel preview)', function () {
    config(['services.plantilla.transport' => 'process']);
    [$course, $phases] = templatesCourse();
    $student = studentOf($course, $phases[1]);

    // The package's engine itself, with the parameters in force: a private seller in Madrid, ITP 6 %.
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/1/calculate", ['datos' => ['Calculadora' => ['B4' => 'Comunidad de Madrid', 'B6' => 'NO']]])
        ->assertOk()->assertJsonPath('data.hoja.celdas.B15.v', 30000);

    // The engine's own refusal comes back as a validation error, as over HTTP.
    LegalParameter::query()->where('code', 'IVA_GENERAL')->update(['value' => 21]);
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/1/calculate", ['datos' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('datos');

    // A missing engine is «not available», never an error page.
    config(['services.plantilla.cli' => base_path('../financials/no-existe.js')]);
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/1/calculate", ['datos' => []])->assertStatus(503);
});

it('plays the preview\'s film from a file when Bunny Stream is not configured, behind the same gate', function () {
    config(['services.bunny.library_id' => null, 'services.bunny.token_key' => null]);
    [$course, $phases] = templatesCourse();
    $lesson = Lesson::factory()->create(['module_id' => $phases[1]->id, 'order' => 1, 'bunny_video_id' => 'modulo-1-es', 'is_free_preview' => false]);
    $student = studentOf($course, $phases[1]);

    // No Bunny and no file location: unavailable, as before.
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/playback")->assertStatus(503);

    config(['services.video_files.base' => '/video/']);
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/playback")->assertOk()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.url', '/video/modulo-1-es.mp4')
        ->assertJsonPath('data.poster', '/video/modulo-1-es.jpg')
        ->assertJsonPath('data.captions', '/video/modulo-1-es.vtt')
        ->assertJsonPath('data.locale', 'es');

    // The file changes how it plays, not who may play it.
    auth()->forgetGuards();
    $this->actingAs(studentOf($course, $phases[2]))->getJson("/api/lessons/{$lesson->id}/playback")->assertForbidden();
});

/*
 * The reference values the admin keeps up to date (financials/valores_de_referencia.json):
 * the 2002 tariffs of the professional template and the values its inputs and
 * the small templates' are presented with.
 */

it('lists every reference value with the package\'s beside it, for admins only', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $reference = $this->actingAs($admin)->getJson('/api/admin/templates')->assertOk()->json('data.reference');

    expect(collect($reference['tariffs'])->pluck('group')->all())
        ->toBe(['oct', 'seguro_decenal', 'avales', 'honorarios_bloque', 'honorarios_chalets', 'honorarios_actuacion', 'honorarios_coeficientes']);
    // The OCT table: 15 tranches by 5 missions, each the package's value until changed.
    $oct = collect($reference['tariffs'])->firstWhere('group', 'oct')['table'];
    expect($oct['rows'])->toHaveCount(15)
        ->and($oct['rows'][0]['cells'][0])->toMatchArray(['cell' => '3ª Construcción!R39', 'value' => 0.0119, 'package' => 0.0119, 'changed' => false]);
    $loan = collect(collect($reference['inputs'])->firstWhere('group', 'plantillas_pequenas')['items'])->firstWhere('cell', 'Calculadora!B6');
    expect($loan)->toMatchArray(['template' => '3', 'label' => 'Tipo de interés anual', 'package' => 0.045]);

    $this->actingAs(User::factory()->create())->getJson('/api/admin/templates')->assertForbidden();
});

it('saves a value with its history, and the package\'s value again removes the change', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ana Admin']);
    $url = '/api/admin/templates/reference';

    $this->actingAs($admin)->putJson($url, ['values' => ['profesional|3ª Terrenos|J24' => 0.01]])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->actingAs($admin)->putJson($url, ['confirmed' => true, 'values' => ['profesional|3ª Terrenos|J24' => 7]])
        ->assertUnprocessable();
    $this->actingAs($admin)->putJson($url, ['confirmed' => true, 'values' => ['profesional|Datos de Partida|C9' => 1]])
        ->assertUnprocessable()->assertJsonValidationErrors('values');

    $avales = fn (array $data) => collect(collect($data['reference']['tariffs'])->firstWhere('group', 'avales')['items'])->firstWhere('cell', '3ª Terrenos!J24');
    $data = $this->actingAs($admin)->putJson($url, ['confirmed' => true, 'values' => ['profesional|3ª Terrenos|J24' => 0.01]])->assertOk()->json('data');
    expect($avales($data))->toMatchArray(['value' => 0.01, 'package' => 0.0075, 'changed' => true])
        ->and($data['reference']['history'][0])->toMatchArray(['old_value' => 0.0075, 'new_value' => 0.01, 'by' => 'Ana Admin'])
        ->and($data['reference']['reviewed_by'])->toBe('Ana Admin');

    $data = $this->actingAs($admin)->putJson($url, ['confirmed' => true, 'values' => ['profesional|3ª Terrenos|J24' => 0.0075]])->assertOk()->json('data');
    expect($avales($data)['changed'])->toBeFalse()
        ->and(DB::table('template_reference_values')->count())->toBe(0)
        ->and(DB::table('template_reference_changes')->count())->toBe(2);
});

it('sends the tariffs the admin changed with every calculation of the professional template, and writes them in its Excel', function () {
    Http::fake(['*' => Http::response(['calculo' => [], 'parametros' => [], 'resumen' => [], 'vista' => [], 'hojas' => []])]);
    [$course, $phases, $financials] = templatesCourse();
    $buyer = studentOf($course, $financials);
    $admin = User::factory()->create(['role' => 'admin']);
    app(ReferenceValues::class)->save($admin, ['profesional|3ª Construcción|AI78' => 0.006, 'profesional|Datos de Partida|D147' => 0.04]);

    $this->actingAs($buyer)->postJson("/api/courses/{$course->slug}/financials/calculate", ['datos' => ['Datos de Partida' => ['C9' => 24]]])->assertOk();
    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $body['tarifas'] === ['3ª Construcción' => ['AI78' => 0.006]]
            // The loan's rate, left empty by the student, is the admin's.
            && $body['datos']['Datos de Partida'] === ['C9' => 24, 'D147' => 0.04];
    });

    // The form presents the admin's value.
    $inputs = $this->actingAs($buyer)->getJson("/api/courses/{$course->slug}/financials/inputs")->assertOk()->json('data.entradas');
    expect(collect($inputs)->firstWhere('celda', 'D147')['defecto'])->toBe(0.04);

    $path = $this->actingAs($buyer)->post("/api/courses/{$course->slug}/financials/excel", ['datos' => ['Datos de Partida' => ['C9' => 24]]])
        ->assertOk()->getFile()->getPathname();
    expect(workbookCell($path, '3ª Construcción', 'AI78'))->toBe(['number', 0.006])
        ->and(workbookCell($path, 'Datos de Partida', 'D147'))->toBe(['number', 0.04]);
});

it('presents a small template\'s inputs with the admin\'s values and calculates with them when left empty', function () {
    Http::fake(['*/pequenas/3/calcular' => Http::response(['calculo' => [], 'parametros' => [], 'hoja' => ['nombre' => 'Calculadora', 'celdas' => []]])]);
    [$course, $phases] = templatesCourse();
    $student = studentOf($course, $phases[5]);
    app(ReferenceValues::class)->save(User::factory()->create(['role' => 'admin']), ['3|Calculadora|B6' => 0.039]);

    $inputs = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/templates/3")->assertOk()->json('data.entradas');
    expect(collect($inputs)->firstWhere('celda', 'B6')['defecto'])->toBe(0.039);

    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/3/calculate", ['datos' => ['Calculadora' => ['B4' => 3000000]]])->assertOk();
    Http::assertSent(fn (Request $request) => json_decode($request->body(), true)['datos'] === ['Calculadora' => ['B4' => 3000000, 'B6' => 0.039]]);

    // What the student types wins.
    $this->actingAs($student)->postJson("/api/courses/{$course->slug}/templates/3/calculate", ['datos' => ['Calculadora' => ['B4' => 3000000, 'B6' => 0.05]]])->assertOk();
    Http::assertSent(fn (Request $request) => json_decode($request->body(), true)['datos'] === ['Calculadora' => ['B4' => 3000000, 'B6' => 0.05]]);
});
