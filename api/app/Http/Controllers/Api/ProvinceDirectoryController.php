<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Province;
use App\Models\ProvinceGuide;
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
        $data = $guide->data;

        $accessible = $user->isAdmin()
            ? $course->modules()->pluck('id')
            : $user->accessibleModuleIds($course);
        $fullAccess = $user->isAdmin() || $user->hasFullAccessTo($course);

        $phases = [];
        $course->modules()->where('kind', 'phase')->orderBy('order')->get()
            ->each(function (Module $module) use (&$phases, $guide, $accessible) {
                $rows = $guide->phaseRows($module->order);
                $owned = $accessible->contains($module->id);
                $phases[] = [
                    'phase' => $module->order,
                    'module_id' => $module->id,
                    'is_locked' => ! $owned,
                    'rows_count' => count($rows),
                    'rows' => $owned
                        ? array_map(fn (array $row) => ['name' => $row[0], 'purpose' => $row[1]], $rows)
                        : [],
                ];
            });

        return response()->json(['data' => [
            'province' => ['id' => $province->id, 'code' => $province->code, 'name' => $province->localizedName()],
            'name' => $data['prov'],
            'capital' => $data['cap'],
            'ccaa' => $data['ccaa'],
            'urban_law' => $data['ley'],
            'first_occupation' => $data['ocup'],
            'habitability_certificate' => $data['cedula'],
            'itp' => $data['itp'],
            'ajd' => $data['ajd'],
            'sale_tax' => $data['iva'],
            'foral' => $data['foral'],
            'phases' => $phases,
            'section_html' => $fullAccess ? Markdown::toHtml($guide->section_es) : null,
        ]]);
    }
}
