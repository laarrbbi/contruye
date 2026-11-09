<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\LegalParameters;
use App\Services\TemplateEngine;
use App\Support\Financials\TemplateCatalog;
use App\Support\Financials\XlsxFiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The five small templates included in the course, each in its module
 * (Plantilla 1 in module 1, 2 in 2, 3 in 5, 4 in 7, 5 in 8): a calculator on
 * the platform, generated from the template's dictionary and calculated by the
 * package's engine with the legal parameters in force, and the template in
 * Excel with the student's inputs. Each is for the owners of its module.
 */
class SmallTemplateController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly LegalParameters $legal,
    ) {}

    /** The five, with the module each belongs to and whether this student has it. */
    public function index(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);
        $modules = $course->includedModules()->get()->keyBy('order');

        return response()->json(['data' => collect(TemplateCatalog::fromConfig()->small())->map(function (array $template) use ($modules, $user) {
            $module = $modules->get($template['module_order']);

            return [
                'number' => $template['number'],
                'title' => $template['title'],
                'module_id' => $module?->id,
                'module_order' => $template['module_order'],
                'is_locked' => $module === null || ! ($user->isAdmin() || $user->hasAccessToModule($module)),
            ];
        })->values()]);
    }

    public function show(Request $request, Course $course, int $number): JsonResponse
    {
        [$template, $module] = $this->authorizeTemplate($request->user(), $course, $number);
        $dictionary = TemplateCatalog::fromConfig()->smallDictionary($number);

        return response()->json(['data' => [
            'number' => $number,
            'title' => $template['title'],
            'module_id' => $module->id,
            'excel' => $template['excel'],
            'regla' => $dictionary['regla'],
            'porcentajes' => $dictionary['porcentajes'],
            'entradas' => $dictionary['entradas'],
            // What the template applies: read only, with the date of the last review.
            'legal' => $this->legal->view(),
        ]]);
    }

    public function calculate(Request $request, Course $course, int $number): JsonResponse
    {
        $this->authorizeTemplate($request->user(), $course, $number);
        $catalog = TemplateCatalog::fromConfig();
        $datos = $catalog->inputs($catalog->smallDictionary($number), $request->validate(['datos' => ['present', 'array']])['datos'], 'datos');

        return response()->json(['data' => $this->engine->small($number, $datos)]);
    }

    /** The template in Excel: a copy with the student's inputs and the legal parameters in force. */
    public function excel(Request $request, Course $course, int $number): BinaryFileResponse
    {
        [$template] = $this->authorizeTemplate($request->user(), $course, $number);
        $catalog = TemplateCatalog::fromConfig();
        $dictionary = $catalog->smallDictionary($number);
        $datos = $catalog->inputs($dictionary, $request->validate(['datos' => ['present', 'array']])['datos'], 'datos');
        $cells = $catalog->legalCells();

        $path = XlsxFiller::fill($catalog->smallWorkbook($number), [
            ...$catalog->toWorkbook($dictionary, $datos),
            $cells['sheet'] => $this->legal->cellValues($cells),
        ]);

        return response()->download($path, $template['excel'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * The template and its module, or 404 for a number the package does not
     * have, and 403 for a student who does not own the module.
     *
     * @return array{0: array<string, mixed>, 1: Module}
     */
    private function authorizeTemplate(User $user, Course $course, int $number): array
    {
        $template = TemplateCatalog::fromConfig()->small()[$number] ?? null;
        abort_if($template === null, 404);

        $module = $course->includedModules()->where('order', $template['module_order'])->first();
        abort_if($module === null, 404);

        $this->authorizeModuleAccess($user, $module);

        return [$template, $module];
    }
}
