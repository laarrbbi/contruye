<?php

use App\Models\ChecklistItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CurriculumV2Seeder;
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
    $this->seed([CurriculumV2Seeder::class, ChecklistSeeder::class]);
    $course = Course::query()->firstOrFail();
    $one = Module::query()->where('course_id', $course->id)->where('order', 1)->firstOrFail();
    $two = Module::query()->where('course_id', $course->id)->where('order', 2)->firstOrFail();

    // Alicante, in the Comunitat Valenciana (the provinces come with the migrations).
    $provinceId = (int) DB::table('provinces')->where('code', 3)->value('id');
    DB::table('province_guides')->updateOrInsert(['province_id' => $provinceId], ['ccaa' => 'Comunitat Valenciana', 'section_es' => '', 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    $student = User::factory()->create(['province_id' => $provinceId]);
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => $one->id]);
    $firstItem = ChecklistItem::query()->where('module_id', $one->id)->orderBy('order')->firstOrFail();
    $student->checkedChecklistItems()->attach($firstItem->id);

    $this->getJson("/api/courses/{$course->slug}/world")->assertUnauthorized();

    $overview = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/world")->assertOk()->json('data');
    expect($overview['start'])->toBe(config('course_world.start'))
        ->and(collect($overview['modules'])->firstWhere('order', 1))->toMatchArray(['id' => $one->id, 'is_locked' => false, 'steps' => count(config('course_world.steps.1'))])
        ->and(collect($overview['modules'])->firstWhere('order', 2))->toMatchArray(['is_locked' => true])
        ->and(collect($overview['modules'])->pluck('order')->all())->toBe(range(1, 12));

    $world = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/modules/{$one->id}/world")->assertOk()->json('data');
    expect($world['steps'])->toHaveCount(count(config('course_world.steps.1')))
        ->and($world['start'])->toBe(config('course_world.start'))
        ->and($world['region'])->toMatchArray(['name' => 'Comunitat Valenciana'])
        ->and($world['checklist_total'])->toBe(16);

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
    $this->actingAs($admin)->getJson("/api/courses/{$course->slug}/modules/{$one->id}/world")->assertOk();
});
