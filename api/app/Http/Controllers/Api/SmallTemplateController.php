<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\LegalParameters;
use App\Services\ReferenceValues;
use App\Services\TemplateEngine;
use App\Support\Financials\TemplateCatalog;
use App\Support\Financials\XlsxFiller;
use App\Support\Financials\XlsxFormats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The templates with a calculator included in the course, each in its
 * module: the five small ones (Plantilla 1 in module 1, 2 in 2, 3 in 5, 4 in
 * 7, 5 in 8), by their number, and the professional's «números gordos del
 * solar» (Module 1), by its id. For each, a calculator on the platform,
 * generated from the template's dictionary and calculated by the package's
 * engine (with the legal parameters in force, where the template has them),
 * and the template in Excel with the student's inputs. Each is for the
 * owners of its module.
 */
class SmallTemplateController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly LegalParameters $legal,
        private readonly ReferenceValues $reference,
    ) {}

    /** The calculators, with the module each belongs to and whether this student has it. */
    public function index(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);
        $modules = $course->includedModules()->get()->keyBy('order');
        $catalog = TemplateCatalog::fromConfig();
        $keys = [
            ...array_map('strval', array_keys($catalog->small())),
            ...array_keys(array_filter($catalog->professionals(), fn (array $t) => $t['template'] !== null)),
        ];

        return response()->json(['data' => collect($keys)->map(function (string $key) use ($catalog, $modules, $user) {
            $template = $catalog->calculator($key);
            $module = $modules->get($template['module_order']);

            return [
                'key' => $key,
                'number' => $template['number'],
                'title' => $template['title'],
                'module_id' => $module?->id,
                'module_order' => $template['module_order'],
                'is_locked' => $module === null || ! ($user->isAdmin() || $user->hasAccessToModule($module)),
            ];
        })->values()]);
    }

    public function show(Request $request, Course $course, string $key): JsonResponse
    {
        [$template, $module] = $this->authorizeTemplate($request->user(), $course, $key);
        $catalog = TemplateCatalog::fromConfig();
        $dictionary = $catalog->calculatorDictionary($template);

        return response()->json(['data' => [
            'key' => $key,
            'number' => $template['number'],
            'title' => $template['title'],
            'module_id' => $module->id,
            'excel' => $template['excel'],
            'regla' => $dictionary['regla'],
            'porcentajes' => $dictionary['porcentajes'],
            // Presented with the reference values the admin keeps up to date.
            'entradas' => $this->reference->presented($key, $dictionary['entradas']),
            // What the template applies: read only, with the date of the last
            // review. None for a template with no taxes in it.
            'legal' => $catalog->calculatorHasLegalSheet($template) ? $this->legal->view() : null,
        ]]);
    }

    public function calculate(Request $request, Course $course, string $key): JsonResponse
    {
        [$template] = $this->authorizeTemplate($request->user(), $course, $key);
        $catalog = TemplateCatalog::fromConfig();
        $datos = $catalog->inputs($catalog->calculatorDictionary($template), $request->validate(['datos' => ['present', 'array']])['datos'], 'datos');
        $result = $this->engine->small($key, $datos);

        // The professional's «números gordos» come in JSON without the number
        // format of their results: each is shown as the delivered .xlsx
        // formats it (#,##0.00 "€", 0.00%), as Excel would.
        if ($template['number'] === null && is_array($result['hoja']['celdas'] ?? null)) {
            $formats = XlsxFormats::of($catalog->calculatorWorkbook($template), (string) ($result['hoja']['nombre'] ?? ''));
            foreach ($result['hoja']['celdas'] as $address => $cell) {
                if (is_array($cell) && ($cell['nf'] ?? null) === null && isset($formats[$address])) {
                    $result['hoja']['celdas'][$address]['nf'] = $formats[$address];
                }
            }
        }

        return response()->json(['data' => $result]);
    }

    /** The template in Excel: a copy with the student's inputs and, where it has them, the legal parameters in force. */
    public function excel(Request $request, Course $course, string $key): BinaryFileResponse
    {
        [$template] = $this->authorizeTemplate($request->user(), $course, $key);
        $catalog = TemplateCatalog::fromConfig();
        $dictionary = $catalog->calculatorDictionary($template);
        $datos = $catalog->inputs($dictionary, $request->validate(['datos' => ['present', 'array']])['datos'], 'datos');

        $values = $catalog->toWorkbook($dictionary, $this->reference->withDefaults($key, $datos));
        if ($catalog->calculatorHasLegalSheet($template)) {
            $cells = $catalog->legalCells();
            $values[$cells['sheet']] = $this->legal->cellValues($cells);
        }

        $path = XlsxFiller::fill($catalog->calculatorWorkbook($template), $values);

        return response()->download($path, $template['excel'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * The template and its module, or 404 for a key the package does not
     * have, and 403 for a student who does not own the module.
     *
     * @return array{0: array{key: string, number: int|null, title: string, module_order: int, excel: string, template: string, inputs: string}, 1: Module}
     */
    private function authorizeTemplate(User $user, Course $course, string $key): array
    {
        $template = TemplateCatalog::fromConfig()->calculator($key);
        abort_if($template === null, 404);

        $module = $course->includedModules()->where('order', $template['module_order'])->first();
        abort_if($module === null, 404);

        $this->authorizeModuleAccess($user, $module);

        return [$template, $module];
    }
}
