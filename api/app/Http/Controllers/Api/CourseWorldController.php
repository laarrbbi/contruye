<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\Course;
use App\Models\LegalParameter;
use App\Models\LegalRegionRate;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ProvinceGuide;
use App\Models\User;
use App\Support\Course\LessonResources;
use App\Support\Markdown;
use App\Support\Protection\ContentFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The 3D world of the course (config/course_world.php).
 *
 * overview(): what stands on the plot after each module and which modules
 * have their script, for any student of the course. Geometry only: no
 * course text, so a student sees the whole promotion grow, including the
 * modules they have not bought.
 *
 * module(): a module's script, step after step: the lesson's own words, the
 * papers, taxes (at the rates in force, ITP and AJD those of the student's
 * region), the checklist items with the student's ticks, the place the camera
 * flies to and what gets built. Behind the module's gate and metered, like
 * the lesson's text; the quotes carry the reader's fingerprint.
 */
class CourseWorldController extends Controller
{
    use AuthorizesCourseAccess;

    /** Legal parameters a step can name by code. */
    private const PARAMETERS = [
        'iva_general' => 'IVA_GENERAL',
        'iva_reducido' => 'IVA_REDUCIDO',
        'ajd_prestamo' => 'AJD_PRESTAMO',
        'ips' => 'IPS',
        'interes_legal' => 'INTERES_LEGAL',
    ];

    public function overview(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);
        $accessible = $user->isAdmin() ? $course->modules()->pluck('id') : $user->accessibleModuleIds($course);
        $config = config('course_world');

        $modules = $course->modules()->where('kind', '!=', Module::KIND_FINANCIALS)->orderBy('order')->get()
            ->map(fn (Module $module) => [
                'id' => $module->id,
                'order' => $module->order,
                'title' => $module->localizedTitle(),
                'is_locked' => ! $accessible->contains($module->id),
                'end' => $config['modules'][$module->order]['end'] ?? [],
                'dusk' => (float) ($config['modules'][$module->order]['dusk'] ?? 0),
                'steps' => count($config['steps'][$module->order] ?? []),
            ]);

        return response()->json(['data' => [
            'start' => $config['start'],
            'modules' => $modules->values(),
        ]]);
    }

    public function module(Request $request, Course $course, Module $module): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($module->course_id !== $course->id, 404);
        $this->authorizeModuleAccess($user, $module);

        $config = config('course_world');
        $script = $config['steps'][$module->order] ?? [];
        abort_if($script === [], 404);

        $lessons = $module->lessons()->orderBy('order')->get()->keyBy(fn (Lesson $l) => LessonResources::number($l));
        $items = ChecklistItem::query()->where('module_id', $module->id)->orderBy('order')->get()->values();
        $checked = $user->checkedChecklistItems()->where('checklist_items.module_id', $module->id)->pluck('checklist_items.id')->all();
        $parameters = LegalParameter::query()->pluck('value', 'code');
        $region = $this->region($user);
        $previous = $course->modules()->where('kind', '!=', Module::KIND_FINANCIALS)->where('order', '<', $module->order)->orderByDesc('order')->value('order');

        $steps = [];
        foreach ($script as $i => $step) {
            $lesson = $lessons[$step['lesson']] ?? null;
            $steps[] = [
                'key' => "{$module->order}.".($i + 1),
                'lesson' => $lesson === null ? null : [
                    'id' => $lesson->id,
                    'number' => $step['lesson'],
                    'title' => $lesson->localizedTitle(),
                    'has_video' => $lesson->videoFor(app()->getLocale()) !== null,
                ],
                'title' => $step['title'],
                'quote_html' => ContentFingerprint::stamp(Markdown::toHtml(self::readable($step['quote']), glossary: true), $user),
                'place' => $step['place'],
                'documents' => $step['documents'] ?? [],
                'people' => $step['people'] ?? [],
                'figures' => $step['figures'] ?? [],
                'plazo' => $step['plazo'] ?? null,
                'taxes' => array_map(fn (array $tax) => $this->tax($tax, $parameters->all(), $region), $step['taxes'] ?? []),
                'checklist' => array_values(array_filter(array_map(function (int $order) use ($items, $checked) {
                    $item = $items[$order - 1] ?? null;

                    return $item === null ? null : ['id' => $item->id, 'order' => $order, 'text_html' => Markdown::inline($item->localizedText(), glossary: true), 'checked' => in_array($item->id, $checked, true)];
                }, $step['checklist'] ?? []))),
                'adds' => $step['adds'] ?? [],
                'removes' => $step['removes'] ?? [],
                'show' => $step['show'] ?? [],
                'emit' => $step['emit'] ?? [],
                'cutaway' => (bool) ($step['cutaway'] ?? false),
            ];
        }

        return response()->json(['data' => [
            'module' => ['id' => $module->id, 'order' => $module->order, 'title' => $module->localizedTitle()],
            // Where the plot stands when the module begins: where the one before ended.
            'start' => $previous === null ? $config['start'] : ($config['modules'][$previous]['end'] ?? $config['start']),
            'start_dusk' => (float) ($previous === null ? 0 : ($config['modules'][$previous]['dusk'] ?? 0)),
            'end' => $config['modules'][$module->order]['end'] ?? [],
            'region' => $region === null ? null : ['name' => $region->name, 'itp' => (float) $region->itp, 'ajd' => (float) $region->ajd],
            // The course is written in Spanish; the 3D script's translations come with the course's (traducciones/).
            'content_locale' => 'es',
            'steps' => $steps,
            'checklist_total' => $items->count(),
        ]]);
    }

    /**
     * A tax as the step shows it: by code, at the rate in force (ITP and AJD
     * of the student's region); by name only, as the lesson writes it, when
     * the platform keeps no rate for it (ICIO, plusvalía…).
     *
     * @param  array{code?: string, name?: string}  $tax
     * @param  array<string, mixed>  $parameters
     * @return array{code: ?string, name: string, rate: ?float, region: ?string}
     */
    private function tax(array $tax, array $parameters, ?LegalRegionRate $region): array
    {
        $code = $tax['code'] ?? null;
        $name = $tax['name'] ?? strtoupper((string) $code);
        if ($code === 'itp' || $code === 'ajd') {
            return ['code' => $code, 'name' => $name, 'rate' => $region === null ? null : (float) $region->{$code}, 'region' => $region?->name];
        }
        $parameter = self::PARAMETERS[$code] ?? null;

        return ['code' => $code, 'name' => $name, 'rate' => $parameter !== null && isset($parameters[$parameter]) ? (float) $parameters[$parameter] : null, 'region' => null];
    }

    /**
     * A row of one of the lesson's tables, read as a line: its cells joined
     * by a middle dot, every word as written.
     */
    private static function readable(string $quote): string
    {
        $quote = trim($quote);
        if (! str_starts_with($quote, '|')) {
            return $quote;
        }

        return implode(' · ', array_map('trim', explode('|', trim($quote, '| '))));
    }

    /** The student's autonomous community, from the province they chose. */
    private function region(User $user): ?LegalRegionRate
    {
        if ($user->province_id === null) {
            return null;
        }
        $ccaa = ProvinceGuide::query()->where('province_id', $user->province_id)->value('ccaa');

        return $ccaa === null ? null : LegalRegionRate::query()->where('name', $ccaa)->first();
    }
}
