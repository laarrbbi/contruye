<?php

use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderModule;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared fixtures
|--------------------------------------------------------------------------
|
| Helpers used by more than one test file live here, not in whichever file
| happened to need them first. Under `--parallel` each worker loads only its
| own files, so a helper declared in a sibling test simply does not exist —
| which is how this was found.
|
*/

function completeSellerSettings(): void
{
    Setting::setValue('invoicing.seller', [
        'business_name' => '[DEMO] Construye Academy',
        'nif' => 'B12345674',
        'address' => 'Calle Mayor 1, 28013 Madrid',
        'legal_form' => 'S.L.',
        'registry' => 'Inscrita en el Registro Mercantil de Madrid, tomo 1, folio 1, hoja M-1',
        'capital' => '3.000 €',
        'phone' => '600 00 00 00',
        'email' => 'contact@example.es',
        'bank_details' => '',
    ]);
}

function paidModulesOrder(User $student): Order
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 490]);
    $modules = Module::factory()->count(3)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_eur' => 49]);

    // 3 × 49 with the 10 % volume tier → 132, like the demo data.
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 132,
        'discount_percent' => 10,
        'currency' => 'EUR',
        'method' => 'manual_transfer',
        'scope' => 'modules',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    foreach ($modules as $module) {
        OrderModule::query()->create([
            'order_id' => $order->id,
            'module_id' => $module->id,
            'unit_price' => 49,
        ]);
    }

    return $order;
}

function invoicePayload(array $overrides = []): array
{
    return array_merge([
        'buyer_name' => 'Javier Morales',
        'buyer_address' => 'Avenida de la Constitución 12, 41001 Sevilla',
        'iva_rate' => 21,
    ], $overrides);
}

/*
| The company directory (CompanyDirectoryTest)
*/

/** A course with phases 1 (Solar) and 6 (Construcción), one lesson each. */
function directoryCourse(): Course
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 490]);
    foreach ([1, 6] as $order) {
        $module = Module::factory()->create(['course_id' => $course->id, 'order' => $order, 'kind' => 'phase', 'price_eur' => 49]);
        Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]);
    }

    return $course->fresh();
}

function company(array $attributes = []): Company
{
    static $n = 0;
    $n++;

    return Company::create($attributes + ['source' => 'test', 'source_ref' => "ref-{$n}", 'name' => "Empresa {$n}", 'category' => 'constructoras', 'is_visible' => true]);
}

/** A tiny .xlsx with an Orbis-like header row and the given rows. */
function orbisFile(array $rows): string
{
    $header = ["Nom de l'entreprise Latin alphabet", 'NACE Rev. 2 Code principal', 'Code postal Latin Alphabet', 'Ville Latin Alphabet', 'Téléphone', 'Adresse du site internet', 'Numéro BvD', 'N° de Taxe / TVA', 'Effectifs Dernière année disp.'];
    $strings = [];
    $index = function (string $s) use (&$strings) {
        $i = array_search($s, $strings, true);
        if ($i === false) {
            $strings[] = $s;
            $i = count($strings) - 1;
        }

        return $i;
    };
    $xmlRows = '';
    foreach (array_merge([$header], $rows) as $r => $row) {
        $cells = '';
        foreach (array_values($row) as $c => $value) {
            $ref = chr(65 + $c).($r + 1);
            $cells .= "<c r=\"{$ref}\" t=\"s\"><v>{$index((string) $value)}</v></c>";
        }
        $xmlRows .= '<row r="'.($r + 1)."\">{$cells}</row>";
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sst = "<sst xmlns=\"{$ns}\">".implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $strings)).'</sst>';
    $path = tempnam(sys_get_temp_dir(), 'orbis').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/sharedStrings.xml', $sst);
    $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet xmlns=\"{$ns}\"><sheetData>{$xmlRows}</sheetData></worksheet>");
    $zip->close();

    return $path;
}

/*
| The three offers (OffersTest). The migration creates the slots, empty and off.
*/

/** Writes and switches on one of the three offers. */
function liveOffer(string $stage, array $attributes = []): Offer
{
    $offer = Offer::query()->where('stage', $stage)->firstOrFail();
    $offer->update($attributes + [
        'is_active' => true,
        'title_es' => "[DEMO] Oferta {$stage}",
        'body_es' => 'Texto **de la oferta**.',
        'url' => 'https://example.es/oferta',
    ]);

    return $offer->fresh();
}

/** A student with one paid order, paid at $paidAt (now by default). */
function paidStudent(?CarbonInterface $paidAt = null): User
{
    $student = User::factory()->create();
    Order::factory()->create(['user_id' => $student->id, 'status' => 'paid', 'paid_at' => $paidAt ?? now()]);

    return $student;
}

/** The stages of the offers the student's app shows now, in order. */
function offerStages(User $student): array
{
    return collect(test()->actingAs($student)->getJson('/api/me/offers')->assertOk()->json('data'))->pluck('stage')->all();
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
