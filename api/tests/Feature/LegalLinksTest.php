<?php

use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Enrollment;
use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Models\User;
use App\Support\Markdown;

it('links a law to its official text in a new tab, without changing a word', function () {
    $html = Markdown::toHtml('El **RDL 7/2015**, texto refundido de la Ley de Suelo.');

    expect($html)->toContain('<a class="ley" target="_blank" rel="noopener noreferrer" href="https://www.boe.es/buscar/act.php?id=BOE-A-2015-11723"')
        ->and(strip_tags($html))->toBe('El RDL 7/2015, texto refundido de la Ley de Suelo.');
});

it('tells apart two laws that share a number by what the paragraph is about', function () {
    $state = Markdown::toHtml('Regulariza con los procedimientos de la Ley 13/2015 de reforma hipotecaria.');
    $murcia = Markdown::toHtml('Ley 13/2015 de Ordenación Territorial y Urbanística de la Región de Murcia');
    $catalonia = Markdown::inline('Texto Refundido de la Ley de Urbanismo de Cataluña (Decreto Legislativo 1/2010)');
    $castilla = Markdown::inline('Texto Refundido de la LOTAU (TRLOTAU, Decreto Legislativo 1/2010)');

    expect($state)->toContain('BOE-A-2015-7046')->not->toContain('BOE-A-2015-4790')
        ->and($murcia)->toContain('BOE-A-2015-4790')
        ->and($catalonia)->toContain('BOE-A-2010-13883')
        ->and($castilla)->toContain('DOCM-q-2010-90043')->not->toContain('BOE-A-2010-13883');
});

it('links each law once per paragraph, on its number rather than its acronym', function () {
    $html = Markdown::toHtml('El TRLSRU (RDL 7/2015) manda; el TRLSRU otra vez.');

    expect(substr_count($html, 'class="ley"'))->toBe(1)
        ->and($html)->toContain('>RDL 7/2015</a>');
});

it('leaves code, existing links and lookalikes alone', function () {
    $html = Markdown::toHtml('Ver [el RD 105/2008](https://example.es) y `RD 105/2008`. Un Real Decreto Legislativo 1/2004 no es el TROTU-bis, ni «LISTAS».');

    expect($html)->not->toContain('class="ley"');
});

it('links every numbered law the course cites to an official page', function () {
    $pattern = '/\b(?:(?:Real Decreto(?:-ley| Legislativo)?|Decreto(?: Foral)?(?: Legislativo)?|Ley(?: Orgánica| Foral)?|RDL|RD|Orden)\s+(?:[A-Z]{2,4}\/)?\d+\/\d{4}|[A-Z]{2,4}\/\d+\/\d{4})/u';
    $unlinked = [];

    foreach (glob(base_path('../curso/1_contenido/modulos/*.md')) as $file) {
        if (str_starts_with(basename($file), '15_')) {
            continue; // the owner's commercial strategy: not published
        }
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>'.Markdown::toHtml(file_get_contents($file)).'</div>');
        foreach ((new DOMXPath($dom))->query('//text()') as $node) {
            if (preg_match_all($pattern, $node->nodeValue, $matches) === 0) {
                continue;
            }
            $linked = $node->parentNode->nodeName === 'a' && $node->parentNode->getAttribute('class') === 'ley';
            foreach ($matches[0] as $reference) {
                if (! $linked) {
                    $unlinked[] = basename($file).': '.$reference;
                }
            }
        }
    }

    expect($unlinked)->toBe([]);
});

it('only links to official sites, over https', function () {
    $official = ['boe.es', 'gva.es', 'juntadeandalucia.es', 'aragon.es', 'asturias.es', 'tributasenasturias.es', 'caib.es', 'atib.es',
        'gobiernodecanarias.org', 'cantabria.es', 'castillalamancha.es', 'jccm.es', 'jcyl.es', 'gencat.cat', 'juntaex.es', 'xunta.gal',
        'atriga.gal', 'comunidad.madrid', 'carm.es', 'navarra.es', 'euskadi.eus', 'larioja.org', 'araba.eus', 'bizkaia.eus',
        'gipuzkoa.eus', 'agenciatributaria.gob.es'];
    $urls = [...array_column(config('legal.laws'), 'url')];
    foreach (config('legal.regions') as $region) {
        $urls = [...$urls, ...array_column($region['links'], 'url')];
        expect(config('legal.laws.'.$region['planning_law']))->not->toBeNull();
    }

    foreach ($urls as $url) {
        $host = parse_url($url, PHP_URL_HOST);
        expect(parse_url($url, PHP_URL_SCHEME))->toBe('https')
            ->and(collect($official)->contains(fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain)))->toBeTrue("{$url} is not an official site");
    }
});

it('gives each province its community\'s official pages', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);
    // The 52 provinces come with the migration.
    $alicante = Province::query()->where('code', 3)->firstOrFail();
    $ceuta = Province::query()->where('code', 51)->firstOrFail();
    $data = fn (string $key, string $law) => ['prov' => 'x', 'cap' => 'x', 'ccaa' => 'x', 'ccaaKey' => $key, 'ley' => $law, 'ocup' => 'x', 'cedula' => null, 'itp' => '9 % · Ley 5/2025', 'ajd' => '1 %', 'iva' => 'IVA 10 %', 'foral' => null, 'phases' => []];
    ProvinceGuide::query()->create(['province_id' => $alicante->id, 'ccaa' => 'Comunitat Valenciana', 'section_es' => '…', 'data' => $data('VAL', 'Texto Refundido (TRLOTUP, Decreto Legislativo 1/2021)')]);
    ProvinceGuide::query()->create(['province_id' => $ceuta->id, 'ccaa' => 'Ceuta', 'section_es' => '…', 'data' => $data('CEU', 'PGOU + TRLSRU')]);

    $valencia = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/directory/{$alicante->id}")->assertOk();
    $links = collect($valencia->json('data.official_links'));
    expect($links->pluck('kind')->unique()->values()->all())->toBe(['planning', 'housing', 'taxes'])
        ->and($links->pluck('url'))->toContain('https://habitatge.gva.es/es/web/vivienda-y-calidad-en-la-edificacion/preus-maxims-habitatge-protegit-segona-o-posteriors-transmissions')
        ->and($valencia->json('data.urban_law_html'))->toContain('DOGV-r-2021-90283')
        ->and($valencia->json('data.itp_html'))->toContain('BOE-A-2025-11959');

    // No official housing price page in Ceuta: none is made up.
    $kinds = collect($this->actingAs($student)->getJson("/api/courses/{$course->slug}/directory/{$ceuta->id}")->assertOk()->json('data.official_links'))->pluck('kind');
    expect($kinds->all())->toBe(['planning', 'taxes']);
});

it('lists every community\'s official pages under Anexo B', function () {
    $course = Course::factory()->create(['status' => 'published']);
    CourseDocument::query()->create(['course_id' => $course->id, 'slug' => 'anexo-b', 'title_es' => 'Anexo B', 'body_es' => '| CCAA |', 'access' => 'enrolled', 'order' => 1]);
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $regions = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/documents/anexo-b")->assertOk()->json('data.regions');

    expect($regions)->toHaveCount(19)
        ->and(collect($regions)->firstWhere('key', 'PVA')['links'])->toHaveCount(5)
        // Each official title says which language it is in, so the page can mark it: Catalan in Cataluña.
        ->and(collect(collect($regions)->firstWhere('key', 'CAT')['links'])->pluck('lang', 'kind')->all())->toBe(['planning' => 'es', 'housing' => 'ca', 'taxes' => 'ca'])
        ->and(collect(collect($regions)->firstWhere('key', 'VAL')['links'])->pluck('lang')->unique()->all())->toBe(['es']);
});
