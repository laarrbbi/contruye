<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Partner;
use App\Models\User;
use App\Support\Course\CourseSource;
use App\Support\Course\LessonResources;

/*
 * Under each lesson's text: the websites it names, at their official
 * address, and the partners it calls on (config/lesson_resources.php). The
 * course's text is never touched; a link stands only for a name the text says.
 */

/** Every lesson of the package by its number ('1.1', 'F.3'), with its Spanish text. */
function packageLessons(): array
{
    $lessons = [];
    foreach (CourseSource::fromConfig()->modules() as $module) {
        foreach ($module['lessons'] as $lesson) {
            preg_match('/^Lección\s+([0-9]+|F)\.([0-9]+)\b/u', $lesson['title'], $m);
            $lessons["{$m[1]}.{$m[2]}"] = $lesson['title']."\n".$lesson['content'];
        }
    }

    return $lessons;
}

it('lists only what a lesson names, at an https address, with real kinds of partner', function () {
    $lessons = packageLessons();
    $config = config('lesson_resources.lessons');
    $categories = array_keys(config('directory.categories'));

    expect($config)->not->toBeEmpty();
    foreach ($config as $number => $entry) {
        expect($lessons)->toHaveKey($number);
        $urls = [];
        foreach ($entry['links'] ?? [] as $link) {
            // The lesson's own words: a name it does not say gets no link.
            expect(mb_stripos($lessons[$number], $link['mention']))->not->toBeFalse("Lección {$number} does not say «{$link['mention']}»")
                ->and($link['url'])->toStartWith('https://')
                ->and(filter_var($link['url'], FILTER_VALIDATE_URL))->not->toBeFalse()
                ->and($link['name'])->not->toBeEmpty();
            $urls[] = $link['url'];
        }
        expect($urls)->toBe(array_values(array_unique($urls)))
            ->and(array_diff($entry['companies'] ?? [], $categories))->toBe([])
            ->and(array_diff($entry['partners'] ?? [], Partner::CATEGORIES))->toBe([]);
    }

    // The land portals of lesson 1.1, and the real-estate agencies under them.
    expect($config['1.1']['companies'])->toContain('agencias_inmobiliarias')
        ->and($config['1.1']['partners'])->toContain('inmobiliaria');
});

function resourcesSetup(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1, 'kind' => 'phase']);
    $lesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1, 'title_es' => 'Lección 1.1 — Define el producto y encuentra el solar', 'is_free_preview' => true]);
    $other = Module::factory()->create(['course_id' => $course->id, 'order' => 2, 'kind' => 'phase']);
    $plain = Lesson::factory()->create(['module_id' => $module->id, 'order' => 5, 'title_es' => 'Lección 1.9 — Sin recursos', 'is_free_preview' => false]);

    return [$course, $module, $lesson, $other, $plain];
}

it('sends a lesson\'s links with its text, and its partners behind the module\'s gate', function () {
    [$course, $module, $lesson, $other, $plain] = resourcesSetup();
    company(['category' => 'agencias_inmobiliarias', 'name' => 'Agencia del Solar']);
    company(['category' => 'constructoras', 'name' => 'Obras Norte']);
    company(['category' => 'agencias_inmobiliarias', 'name' => 'Oculta', 'is_visible' => false]);

    $owner = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $owner->id, 'course_id' => $course->id, 'module_id' => $module->id]);

    $resources = $this->actingAs($owner)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")
        ->assertOk()->json('data.resources');
    expect($resources['companies'])->toContain('agencias_inmobiliarias')
        ->and($resources['companies_count'])->toBe(1)
        ->and($resources['partners'])->toContain('inmobiliaria');

    // The lesson's companies: only its kinds, only the visible ones.
    $names = collect($this->actingAs($owner)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/companies")
        ->assertOk()->json('data'))->pluck('name')->all();
    expect($names)->toBe(['Agencia del Solar']);

    // A free lesson read without the module: its links, but not the companies.
    $visitor = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $visitor->id, 'course_id' => $course->id, 'module_id' => $other->id]);
    $this->actingAs($visitor)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/content")
        ->assertOk()->assertJsonPath('data.resources.companies.0', 'agencias_inmobiliarias');
    $this->actingAs($visitor)->getJson("/api/courses/{$course->slug}/lessons/{$lesson->id}/companies")->assertForbidden();

    // A lesson that calls on no company has no list.
    expect(LessonResources::payload($plain))->toBe(['links' => [], 'companies' => [], 'companies_count' => 0, 'partners' => []]);
    $this->actingAs($owner)->getJson("/api/courses/{$course->slug}/lessons/{$plain->id}/companies")->assertNotFound();
});
