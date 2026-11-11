<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Support\Legal\RegionalSources;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Anexo A for one province: where to go, phase by phase.
 *
 * The rows of a phase are part of its module and follow the module's gate: a
 * student who owns modules 1 and 4 gets the rows of phases 1 and 4, and the
 * count of the others. The province's whole section of the annex, as written,
 * comes with full access.
 */
class ProvinceDirectoryController extends Controller
{
    use AuthorizesCourseAccess;

    public function show(Request $request, Course $course, Province $province): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        $guide = ProvinceGuide::query()->where('province_id', $province->id)->firstOrFail();
        // The purposes and facts in the reader's language once translated;
        // the names of institutions and laws are as written in any language.
        [$data, $dataLocale] = $guide->localizedData();

        $accessible = $user->isAdmin()
            ? $course->modules()->pluck('id')
            : $user->accessibleModuleIds($course);
        $fullAccess = $user->isAdmin() || $user->hasFullAccessTo($course);

        $phases = [];
        $course->modules()->where('kind', 'phase')->orderBy('order')->get()
            ->each(function (Module $module) use (&$phases, $guide, $data, $accessible) {
                $rows = $guide->phaseRows($module->order, $data);
                $owned = $accessible->contains($module->id);
                $phases[] = [
                    'phase' => $module->order,
                    'module_id' => $module->id,
                    'is_locked' => ! $owned,
                    'rows_count' => count($rows),
                    'rows' => $owned
                        ? array_map(fn (array $row) => [
                            'name' => $row[0],
                            'purpose' => $row[1],
                            // The same text with its laws linked to their official pages.
                            'purpose_html' => Markdown::inline($row[1], glossary: true),
                        ], $rows)
                        : [],
                ];
            });

        return response()->json(['data' => [
            'province' => ['id' => $province->id, 'code' => $province->code, 'name' => $province->localizedName()],
            // The language of the purposes and facts below (Spanish until translated).
            'data_locale' => $dataLocale,
            'name' => $data['prov'],
            'capital' => $data['cap'],
            'ccaa' => $data['ccaa'],
            'urban_law' => $data['ley'],
            'urban_law_html' => Markdown::inline($data['ley'], glossary: true),
            'first_occupation' => $data['ocup'],
            'first_occupation_html' => Markdown::inline($data['ocup'], glossary: true),
            'habitability_certificate' => $data['cedula'],
            'itp' => $data['itp'],
            'itp_html' => Markdown::inline($data['itp'], glossary: true),
            'ajd' => $data['ajd'],
            'sale_tax' => $data['iva'],
            'foral' => $data['foral'],
            // The community's planning law, tax agency and protected-housing prices.
            'official_links' => RegionalSources::for($data['ccaaKey'] ?? null),
            'phases' => $phases,
            'section_html' => $fullAccess ? Markdown::toHtml($guide->section_es, glossary: true) : null,
        ]]);
    }
}
