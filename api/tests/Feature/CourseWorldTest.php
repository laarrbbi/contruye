<?php

use App\Models\ChecklistItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\ProvinceGuideSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The 3D world of the course (config/course_world.php): its script quotes the
 * instructor and names only what the course names; it only calls pieces and
 * places the 3D engine has; and a module's steps reach its owners only.
 */

/** A module's file of the package, as delivered. */
function worldModuleText(int $order): string
{
    $files = glob(rtrim((string) config('curso.path'), '/').'/1_contenido/modulos/'.sprintf('%02d', $order).'_*.md');

    return $files === [] ? '' : (string) file_get_contents($files[0]);
}

/** A lesson's section of a module's file: from its heading to the next one. */
function worldLessonSection(string $text, string $number): string
{
    return preg_match('/^## Lección '.preg_quote($number, '/').' .*?(?=^## |\z)/msu', $text, $m) === 1 ? $m[0] : '';
}

/** @return list<list<string>> the data rows of the first table under a "## " heading */
function worldTableRows(string $text, string $heading): array
{
    if (preg_match('/^## '.preg_quote($heading, '/').'\R(.*?)(?=^## |\z)/msu', $text, $m) !== 1) {
        return [];
    }
    $rows = [];
    foreach (preg_split('/\R/u', $m[1]) as $line) {
        if (str_starts_with($line, '|') && preg_match('/^\|[\s:|-]+\|$/', trim($line)) !== 1) {
            $rows[] = array_map('trim', explode('|', trim($line, "| \t")));
        }
    }

    return array_slice($rows, 1); // the header
}

/** @return list<string> the header cells of the table a row belongs to */
function worldTableHeaderOf(string $text, string $row): array
{
    $lines = preg_split('/\R/u', $text);
    $at = array_search(trim($row), array_map('trim', $lines), true);
    if ($at === false) {
        return [];
    }
    while ($at > 0 && str_starts_with(trim($lines[$at - 1]), '|')) {
        $at--;
    }

    return array_map('trim', explode('|', trim($lines[$at], "| \t")));
}

/** @return list<string> the keys the 3D engine knows: its pieces (site.ts, worldScene.ts) or its places (places.ts) */
function worldEngineKeys(string $kind): array
{
    $dir = base_path('../web/src/components/world3d');
    if ($kind === 'places') {
        preg_match_all("/\\{ key: '([a-z_]+)', x:/", (string) file_get_contents("{$dir}/places.ts"), $m);

        return $m[1];
    }
    $site = (string) file_get_contents("{$dir}/site.ts").(string) file_get_contents("{$dir}/worldScene.ts");
    preg_match_all("/(?:popPiece|risePiece)\\('([a-z.0-9_]+)'|key: '([a-z]+\\.[a-z.0-9_]+)'/", $site, $m);
    $keys = array_values(array_filter(array_merge($m[1], $m[2])));
    foreach (['structure', 'facade'] as $floorPiece) {
        if (str_contains($site, "`site.{$floorPiece}.\${f.index}`")) {
            for ($i = 0; $i < 6; $i++) {
                $keys[] = "site.{$floorPiece}.{$i}";
            }
        }
    }

    return array_values(array_unique($keys));
}

it('quotes the lessons as written and names only what the course names', function () {
    $config = config('course_world');
    expect($config['steps'])->not->toBeEmpty();
    $resources = config('lesson_resources.lessons');
    $provinces = json_decode((string) file_get_contents(rtrim((string) config('curso.path'), '/').'/1_contenido/datos/partners_por_provincia.json'), true);
    expect($provinces)->toHaveCount(52);
    $institutions = ['registro_propiedad' => 'Registro de la Propiedad', 'catastro' => 'Catastro', 'ayuntamiento' => 'Ayuntamiento', 'notaria' => 'Notaría', 'registro_mercantil' => 'Registro Mercantil'];

    foreach ($config['steps'] as $order => $steps) {
        $text = worldModuleText($order);
        $flat = preg_replace('/\s+/u', ' ', $text);
        expect($text)->not->toBe('');
        $checklist = preg_match_all('/^- \[ \] /m', $text);

        foreach ($steps as $i => $step) {
            $where = "Módulo {$order}, paso ".($i + 1);
            expect(str_contains($flat, preg_replace('/\s+/u', ' ', $step['quote'])))->toBeTrue("{$where}: the quote is not the course's text")
                ->and(str_contains($text, "## Lección {$step['lesson']} "))->toBeTrue("{$where}: no Lección {$step['lesson']}")
                ->and($step['title'])->not->toBe('');
            foreach ([...($step['documents'] ?? []), ...($step['people'] ?? []), ...($step['figures'] ?? []), ...array_column($step['taxes'] ?? [], 'name')] as $name) {
                expect(mb_stripos($flat, $name))->not->toBeFalse("{$where}: the course does not name «{$name}»");
            }
            foreach ($step['checklist'] ?? [] as $item) {
                expect($item)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual($checklist);
            }
            if (isset($step['plazo'])) {
                expect(mb_stripos($flat, $step['plazo']))->not->toBeFalse("{$where}: the course gives no plazo «{$step['plazo']}»");
            }

            // The lesson's «Errores típicos», inside the step's own lesson.
            if (isset($step['warning'])) {
                $lesson = preg_replace('/\s+/u', ' ', worldLessonSection($text, $step['lesson']));
                expect(str_contains($lesson, $step['warning']))->toBeTrue("{$where}: the warning is not Lección {$step['lesson']}'s");
            }

            // «Dónde ir» rows exist, and at an institution they are that institution's.
            $whereRows = worldTableRows($text, 'Dónde ir');
            foreach ($step['where'] ?? [] as $row) {
                expect($whereRows)->toHaveKey($row - 1);
                if (isset($institutions[$step['place']])) {
                    expect($whereRows[$row - 1][0])->toContain($institutions[$step['place']]);
                }
            }

            // A table row is shown under its own table's header.
            if (isset($step['columns'])) {
                expect(worldTableHeaderOf($text, $step['quote']))->toBe($step['columns']);
            }

            // A tax's note is the lesson's words on that tax, on its line.
            foreach ($step['taxes'] ?? [] as $tax) {
                if (isset($tax['note'])) {
                    $line = collect(preg_split('/\R/u', $text))->first(fn (string $l) => str_contains($l, $tax['name']));
                    expect($line)->not->toBeNull()->and((string) $line)->toContain($tax['note']);
                }
            }

            // Websites by their name for that lesson (config/lesson_resources.php).
            $names = array_column($resources[$step['lesson']]['links'] ?? [], 'name');
            foreach ($step['links'] ?? [] as $name) {
                expect($names)->toContain($name);
            }

            // Anexo A rows mean the same office in every province.
            foreach ($step['offices'] ?? [] as $row) {
                $purposes = array_values(array_unique(array_map(fn (array $p) => $p['phases'][(string) $order][$row - 1][1] ?? '', $provinces)));
                expect($purposes)->toHaveCount(1)->and($purposes[0])->not->toBe('');
            }
        }
    }
});

it('calls only on pieces and places the 3D engine has', function () {
    $pieces = worldEngineKeys('pieces');
    $places = worldEngineKeys('places');
    $config = config('course_world');

    expect($pieces)->toContain('plot.ground', 'plot.fence', 'site.structure.5', 'site.facade.0', 'site.keys', 'place.oficina_ventas')
        ->and($places)->toContain('site', 'ayuntamiento', 'notaria', 'registro_propiedad', 'hacienda');

    $used = $config['start'];
    foreach ($config['modules'] as $module) {
        $used = [...$used, ...$module['end']];
    }
    foreach ($config['steps'] as $steps) {
        foreach ($steps as $step) {
            $used = [...$used, ...($step['adds'] ?? []), ...($step['removes'] ?? []), ...($step['show'] ?? [])];
            expect($places)->toContain($step['place']);
            expect(array_diff($step['emit'] ?? [], ['paper', 'tax', 'stamp']))->toBe([]);
            foreach ($step['taxes'] ?? [] as $tax) {
                expect(! isset($tax['code']) || in_array($tax['code'], ['itp', 'ajd', 'iva_general', 'iva_reducido', 'ajd_prestamo', 'ips', 'interes_legal'], true))->toBeTrue();
            }
        }
    }
    expect(array_values(array_diff(array_unique($used), $pieces)))->toBe([]);

    // Every module's script ends where the module says it ends.
    foreach ($config['steps'] as $order => $steps) {
        $previous = $order === 1 ? $config['start'] : $config['modules'][$order - 1]['end'];
        $state = array_flip($previous);
        foreach ($steps as $step) {
            foreach ($step['adds'] ?? [] as $key) {
                $state[$key] = true;
            }
            foreach ($step['removes'] ?? [] as $key) {
                unset($state[$key]);
            }
        }
        $end = array_keys($state);
        sort($end);
        $expected = $config['modules'][$order]['end'];
        sort($expected);
        expect($end)->toBe($expected);
    }
});

it('serves a module\'s steps to its owners only, with the student\'s regional rates and ticks', function () {
    $this->seed([CurriculumV2Seeder::class, ChecklistSeeder::class, ProvinceGuideSeeder::class]);
    $course = Course::query()->firstOrFail();
    $one = Module::query()->where('course_id', $course->id)->where('order', 1)->firstOrFail();
    $two = Module::query()->where('course_id', $course->id)->where('order', 2)->firstOrFail();

    // Alicante, in the Comunitat Valenciana (the provinces come with the migrations, its guide with Anexo A).
    $provinceId = (int) DB::table('provinces')->where('code', 3)->value('id');
    expect(DB::table('province_guides')->where('province_id', $provinceId)->value('ccaa'))->toBe('Comunitat Valenciana');

    $student = User::factory()->create(['province_id' => $provinceId]);
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $one->id]);
    $firstItem = ChecklistItem::query()->where('module_id', $one->id)->orderBy('order')->firstOrFail();
    $student->checkedChecklistItems()->attach($firstItem->id);

    $this->getJson("/api/courses/{$course->slug}/world")->assertUnauthorized();

    $overview = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/world")->assertOk()->json('data');
    expect($overview['start'])->toBe(config('course_world.start'))
        ->and(collect($overview['modules'])->firstWhere('order', 1))->toMatchArray(['id' => $one->id, 'is_locked' => false, 'steps' => count(config('course_world.steps.1'))])
        ->and(collect($overview['modules'])->firstWhere('order', 2))->toMatchArray(['is_locked' => true])
        ->and(collect($overview['modules'])->pluck('order')->all())->toBe(range(1, 12))
        // Each module named as «Las 12 fases» names its phase.
        ->and($overview['modules'][0]['phase'])->toBe(['name' => 'Solar', 'outcome' => 'Solar comprado (u opcionado) sin sorpresas'])
        ->and($overview['modules'][11]['phase']['name'])->toBe('Postventa y cierre');

    $world = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$one->id}/world")->assertOk()->json('data');
    expect($world['steps'])->toHaveCount(count(config('course_world.steps.1')))
        ->and($world['start'])->toBe(config('course_world.start'))
        ->and($world['region'])->toMatchArray(['name' => 'Comunitat Valenciana'])
        ->and($world['checklist_total'])->toBe(16)
        // Every item with its tick, the ones no step names included (the counter reaches 16 of 16).
        ->and($world['checklist'])->toHaveCount(16)
        ->and(collect($world['checklist'])->firstWhere('id', $firstItem->id)['checked'])->toBeTrue()
        ->and($world['province'])->toBe(Province::query()->findOrFail($provinceId)->localizedName())
        ->and($world['module']['description_html'])->toContain('Objetivo')->toContain('Resultado');

    // At the Ayuntamiento: the «Dónde ir» row, as the module's table writes it.
    $cedula = $world['steps'][8];
    expect($cedula['where'][0]['institution_html'])->toContain('<strong>Ayuntamiento · Urbanismo</strong>')->not->toContain('**')
        ->and($cedula['where'][0]['ask_html'])->toContain('Informe o cédula urbanística')
        ->and($cedula['where'][0]['plazo_html'])->toContain('1-3 meses')
        ->and($cedula['offices'][0]['name'])->toBe('Ayuntamiento de Alicante y municipios de la provincia · Urbanismo')
        ->and($cedula['warning_html'])->toContain('Errores típicos');

    // The student's own Catastro office, and the website the lesson names.
    $catastro = $world['steps'][11];
    expect($catastro['offices'][0]['name'])->toBe('Gerencia del Catastro de Alicante')
        ->and($catastro['links'])->toBe([['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/']]);

    // A table row under its table's header; a tax with the lesson's note.
    $vat = $world['steps'][19];
    expect(array_column($vat['quote_rows'], 'label'))->toBe(['Vendedor y tipo de suelo', 'Impuesto', '¿Recuperable?'])
        ->and($vat['quote_rows'][1]['value_html'])->toContain('IVA 21 %')
        ->and($vat['quote_rows'][0]['value_html'])->toContain('<strong>terreno edificable</strong>')->not->toContain('**');
    $plusvalia = collect($world['steps'][21]['taxes'])->firstWhere('name', 'Plusvalía municipal (IIVTNU)');
    expect($plusvalia['note'])->toBe('la paga el vendedor');

    $first = $world['steps'][0];
    expect($first['lesson']['number'])->toBe('1.1')
        ->and($first['quote_html'])->toContain('Antes de buscar, define qué vas a promover.')
        ->and($first['place'])->toBe('promotor');

    // ITP of the student's region, IVA at the rate in force.
    $itp = collect($world['steps'])->flatMap(fn ($s) => $s['taxes'])->firstWhere('code', 'itp');
    $iva = collect($world['steps'])->flatMap(fn ($s) => $s['taxes'])->firstWhere('code', 'iva_general');
    expect($itp['region'])->toBe('Comunitat Valenciana')
        ->and($itp['rate'])->toBe((float) DB::table('legal_region_rates')->where('name', 'Comunitat Valenciana')->value('itp'))
        ->and($iva['rate'])->toBe((float) DB::table('legal_parameters')->where('code', 'IVA_GENERAL')->value('value'));

    // The student's own ticks travel with the checklist items.
    $ticked = collect($world['steps'])->flatMap(fn ($s) => $s['checklist'])->firstWhere('id', $firstItem->id);
    expect($ticked['checked'])->toBeTrue();

    // A table row of the lesson reads as a line.
    $row = collect($world['steps'])->firstWhere('title', 'Si vende un particular: ITP');
    expect($row['quote_html'])->toContain('Particular')->not->toContain('|');

    // Another module: locked for this student; and no script yet is a 404 for its owner.
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$two->id}/world")->assertForbidden();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->getJson("/api/courses/{$course->slug}/modules/{$two->id}/world")->assertNotFound();
    // No province chosen: the step says so instead of showing someone else's office.
    $adminWorld = $this->actingAs($admin)->getJson("/api/courses/{$course->slug}/modules/{$one->id}/world")->assertOk()->json('data');
    expect($adminWorld['steps'][11]['offices'])->toBeNull()
        ->and($adminWorld['steps'][0]['offices'])->toBe([]);
});
