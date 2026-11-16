<?php

use App\Models\Course;
use App\Models\Module;
use App\Support\Course\CourseTables;
use Database\Seeders\CurriculumV2Seeder;

/*
 * The course's own tables, read back from the stored course text: «Las 12
 * fases» of the introduction and a module's «Dónde ir», cell for cell as the
 * package writes them.
 */

function courseFile(string $pattern): string
{
    $files = glob(rtrim((string) config('curso.path'), '/').'/1_contenido/modulos/'.$pattern);

    return $files === [] ? '' : (string) file_get_contents($files[0]);
}

it('reads «Las 12 fases» as the introduction writes them', function () {
    $this->seed(CurriculumV2Seeder::class);
    $course = Course::query()->firstOrFail();
    $introduction = courseFile('00_*.md');

    $phases = CourseTables::phases($course);
    expect(array_keys($phases))->toBe(range(1, 12))
        ->and($phases[1]['name'])->toBe('Solar')
        ->and($phases[12]['name'])->toBe('Postventa y cierre');
    foreach ($phases as $number => $phase) {
        expect($introduction)->toContain("| {$number} | {$phase['name']} | {$phase['outcome']} |");
    }

    // A translated introduction gives the same twelve, in its language.
    expect(array_keys(CourseTables::phases($course, 'en')))->toBe(range(1, 12));
});

it('reads a module\'s «Dónde ir» rows, numbered as they stand', function () {
    $this->seed(CurriculumV2Seeder::class);
    $one = Module::query()->where('order', 1)->firstOrFail();
    $text = courseFile('01_*.md');

    $rows = CourseTables::whereToGo($one);
    expect($rows)->not->toBeEmpty()->and(array_key_first($rows))->toBe(1);
    foreach ($rows as $row) {
        expect($text)->toContain("| {$row['institution']} | {$row['ask']} | {$row['get']} | {$row['plazo']} |");
    }
    expect($rows[1]['ask'])->toBe('Nota simple de la finca');
});
