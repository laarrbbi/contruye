<?php

use App\Models\Course;
use App\Models\LegalRegionRate;
use Illuminate\Support\Facades\Http;

/*
 * The public sample of Plantilla 1 · Compra del solar (constitution III,
 * amendment of 2026-11-12): four inputs, three results, run by the package's
 * engine with the legal parameters in force. Everything else of the template
 * stays with the owners of Module 1.
 */

const SOLAR = '/api/public/solar-calculator';

/** A full sheet as the engine returns it, every result with a value of its own. */
function solarSheet(): array
{
    return ['calculo' => [], 'parametros' => [], 'hoja' => ['nombre' => 'Calculadora', 'celdas' => [
        'B13' => ['v' => 105000.11], 'B14' => ['v' => 3750.22], 'B15' => ['v' => 0], 'B16' => ['v' => 2500.33],
        'B17' => ['v' => 509250], 'B18' => ['v' => 614250.44], 'B19' => ['v' => 254.625], 'B20' => ['v' => 0.0075],
    ]]];
}

it('offers the template\'s communities, defaults and the rates in force, without an account', function () {
    $this->getJson(SOLAR)
        ->assertOk()
        ->assertJsonCount(19, 'data.communities')
        ->assertJsonPath('data.defaults.community', 'Comunitat Valenciana')
        ->assertJsonPath('data.defaults.seller_company', true)
        ->assertJsonPath('data.labels.land_cost', 'Coste del solar (sin IVA)')
        ->assertJsonPath('data.locked.4', 'Dinero que necesitas el día de la compra')
        ->assertJsonPath('data.vat', 0.21)
        ->assertJsonCount(19, 'data.regions')
        ->assertJsonPath('data.regions.0.name', 'Andalucía');
});

it('gives three results and withholds every other line of the template', function () {
    Http::fake(['*/pequenas/1/calcular' => Http::response(solarSheet())]);

    $response = $this->postJson(SOLAR, ['community' => 'Comunidad de Madrid', 'price' => 500000, 'seller_company' => true, 'buildable_m2' => 2000])
        ->assertOk()
        ->assertExactJson(['data' => ['land_cost' => 509250, 'per_m2' => 254.625, 'tax_rate' => 0.0075, 'tax' => 'AJD']]);

    // The VAT, the AJD and notary lines and the money needed on the day are Module 1's.
    foreach (['105000.11', '3750.22', '2500.33', '614250.44'] as $locked) {
        expect($response->getContent())->not->toContain($locked);
    }

    // Only the four public inputs reach the engine; the template keeps its own defaults for the rest.
    Http::assertSent(fn ($request) => (array) $request->data()['datos'] === ['Calculadora' => [
        'B4' => 'Comunidad de Madrid', 'B5' => 500000, 'B6' => 'SI', 'B7' => 2000,
    ]]);
});

it('refuses what the template would not take', function (array $input, string $field) {
    $valid = ['community' => 'Comunidad de Madrid', 'price' => 500000, 'seller_company' => false, 'buildable_m2' => 2000];

    $this->postJson(SOLAR, [...$valid, ...$input])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'a community the template does not list' => [['community' => 'Bretaña'], 'community'],
    'no price' => [['price' => 0], 'price'],
    'no floor area' => [['buildable_m2' => -5], 'buildable_m2'],
    'a seller that is neither' => [['seller_company' => 'quizá'], 'seller_company'],
]);

it('runs the package\'s own engine with the rates the admin sets', function () {
    config(['services.plantilla.transport' => 'process']);
    $input = ['community' => 'Comunidad de Madrid', 'price' => 500000, 'seller_company' => false, 'buildable_m2' => 2000];

    // A private seller in Madrid: ITP 6 %, notary 0.5 % and due diligence 3,000 € by the template's defaults.
    $this->postJson(SOLAR, $input)->assertOk()
        ->assertJsonPath('data.land_cost', 535500)
        ->assertJsonPath('data.per_m2', 267.75)
        ->assertJsonPath('data.tax_rate', 0.06)
        ->assertJsonPath('data.tax', 'ITP');

    LegalRegionRate::query()->where('name', 'Comunidad de Madrid')->update(['itp' => 0.07]);
    $this->postJson(SOLAR, $input)->assertOk()->assertJsonPath('data.land_cost', 540500);
});

it('serves the calculator\'s page to search engines, with the rates of every community', function () {
    config(['seo.spa_index' => base_path('../web/index.html'), 'app.url' => 'https://construye.example']);

    $html = $this->get('/calculadora-precio-solar')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Calculadora del precio de un solar: ITP, AJD e IVA — Construye Academy</title>')
        ->toContain('<link rel="canonical" href="https://construye.example/calculadora-precio-solar"')
        ->toContain('Calculadora del precio de un solar</h1>')
        // Madrid's ITP, as the legal parameters hold it, written the Spanish way.
        ->toContain('<th scope="row">Comunidad de Madrid</th>')
        ->toContain("<td>6\u{a0}%</td>")
        ->toContain("el IVA (21\u{a0}%)")
        ->not->toContain('noindex');

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
    $types = collect(json_decode($match[1], true)['@graph'])->pluck('@type')->all();
    expect($types)->toContain('WebApplication', 'FAQPage', 'BreadcrumbList');

    // Each language at its own address, with its own words.
    expect($this->get('/en/calculadora-precio-solar')->assertOk()->getContent())
        ->toContain('Building plot price calculator</h1>');
});

it('lists the calculator\'s page in the sitemap and links it from the home page', function () {
    config(['seo.spa_index' => base_path('../web/index.html'), 'app.url' => 'https://construye.example']);
    Course::factory()->create(['slug' => config('seo.main_course'), 'status' => 'published']);

    expect($this->get('/sitemap.xml')->assertOk()->getContent())
        ->toContain('<loc>https://construye.example/calculadora-precio-solar</loc>')
        ->toContain('<loc>https://construye.example/fr/calculadora-precio-solar</loc>');

    expect($this->get('/')->assertOk()->getContent())->toContain('href="/calculadora-precio-solar"');
});
