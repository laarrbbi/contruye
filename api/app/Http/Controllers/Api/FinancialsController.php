<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\FinancialScenario;
use App\Models\Module;
use App\Models\User;
use App\Services\LegalParameters;
use App\Services\ReferenceValues;
use App\Services\TemplateEngine;
use App\Support\Financials\TemplateCatalog;
use App\Support\Financials\XlsxFiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Financials: the professional template (the corrected PLANTILLA_LIBRE),
 * calculated on the server. It is the extra offered at the end of the course:
 * everything here is for the students who bought it (and the admins).
 *
 * The form is built from the template's own dictionary of input cells
 * (entradas.json), and a calculation is the template itself, run by the
 * financials service with its formulas and the legal parameters in force.
 * Only input cells are accepted, here and by the service, so no formula and
 * no legal parameter can be overwritten from here.
 */
class FinancialsController extends Controller
{
    use AuthorizesCourseAccess;

    private const MAX_SCENARIOS = 50;

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly LegalParameters $legal,
        private readonly ReferenceValues $reference,
    ) {}

    public function inputs(Request $request, Course $course): JsonResponse
    {
        $this->authorizeFinancials($request->user(), $course);

        $catalog = TemplateCatalog::fromConfig();

        $dictionary = $catalog->professionalDictionary();

        return response()->json(['data' => [
            ...$dictionary,
            // Presented with the reference values the admin keeps up to date.
            'entradas' => $this->reference->presented(ReferenceValues::PROFESSIONAL, $dictionary['entradas']),
            // The package's worked examples (pruebas/): a promotion of 24
            // homes (motor/ejemplo.js), to start from something that
            // calculates, and the one of the professional's feasibility guide
            // (22 homes), its dates as the template stores them.
            'ejemplos' => [[
                'key' => '24-viviendas',
                'datos' => $catalog->professionalExample(),
            ], [
                'key' => 'guia-profesional',
                'datos' => $catalog->toWorkbook($dictionary, $catalog->professionalGuideExample()),
            ]],
            // What the template applies: read only, with the date of the last review.
            'legal' => $this->legal->view(),
        ]]);
    }

    public function calculate(Request $request, Course $course): JsonResponse
    {
        $this->authorizeFinancials($request->user(), $course);

        $validated = $request->validate([
            'datos' => ['present', 'array'],
            'hojas' => ['sometimes', 'array', 'max:2'],
            'hojas.*' => ['string', 'max:60'],
        ]);
        $catalog = TemplateCatalog::fromConfig();
        $datos = $catalog->inputs($catalog->professionalDictionary(), $validated['datos'], 'datos');

        return response()->json(['data' => $this->engine->professional($datos, $validated['hojas'] ?? [])]);
    }

    /**
     * The corrected PLANTILLA_LIBRE in Excel with the student's inputs and the
     * legal parameters in force written into a copy: the formulas travel
     * untouched, and Excel calculates it on opening.
     */
    public function excel(Request $request, Course $course): BinaryFileResponse
    {
        $this->authorizeFinancials($request->user(), $course);

        $catalog = TemplateCatalog::fromConfig();
        $dictionary = $catalog->professionalDictionary();
        $datos = $catalog->inputs($dictionary, $request->validate(['datos' => ['present', 'array']])['datos'], 'datos');
        $cells = $catalog->legalCells();

        $sheets = $catalog->toWorkbook($dictionary, $this->reference->withDefaults(ReferenceValues::PROFESSIONAL, $datos));
        // The tariffs the admin has brought up to date, in their cells: the
        // file calculates what the platform calculated.
        foreach ($this->reference->tariffsForEngine() as $sheet => $values) {
            $sheets[$sheet] = [...($sheets[$sheet] ?? []), ...$values];
        }

        $path = XlsxFiller::fill($catalog->professionalWorkbook(), [
            ...$sheets,
            $cells['sheet'] => $this->legal->cellValues($cells),
        ]);

        return response()->download($path, 'PLANTILLA_LIBRE_corregida.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function scenarios(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $this->authorizeFinancials($user, $course);

        return response()->json(['data' => FinancialScenario::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->get(['id', 'name', 'inputs', 'updated_at'])]);
    }

    public function storeScenario(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $this->authorizeFinancials($user, $course);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'inputs' => ['present', 'array'],
        ]);

        if (FinancialScenario::query()->where('user_id', $user->id)->count() >= self::MAX_SCENARIOS) {
            throw ValidationException::withMessages(['name' => [__('You have reached the maximum number of saved scenarios. Delete one first.')]]);
        }

        $scenario = FinancialScenario::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'inputs' => $this->scenarioInputs($data['inputs']),
        ]);

        return response()->json(['data' => $scenario->only(['id', 'name', 'inputs', 'updated_at'])], 201);
    }

    public function updateScenario(Request $request, Course $course, FinancialScenario $scenario): JsonResponse
    {
        $user = $request->user();
        $this->authorizeFinancials($user, $course);
        abort_unless($scenario->user_id === $user->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'inputs' => ['present', 'array'],
        ]);

        $scenario->update(['name' => $data['name'], 'inputs' => $this->scenarioInputs($data['inputs'])]);

        return response()->json(['data' => $scenario->only(['id', 'name', 'inputs', 'updated_at'])]);
    }

    public function destroyScenario(Request $request, Course $course, FinancialScenario $scenario): JsonResponse
    {
        $user = $request->user();
        $this->authorizeFinancials($user, $course);
        abort_unless($scenario->user_id === $user->id, 404);

        $scenario->delete();

        return response()->json(['message' => __('Scenario deleted.')]);
    }

    private function authorizeFinancials(User $user, Course $course): void
    {
        $this->authorizeCourseAccess($user, $course);

        /** @var Module|null $module */
        $module = $course->modules()->where('kind', 'financials')->first();
        abort_if($module === null, 404);

        $this->authorizeModuleAccess($user, $module);
    }

    /**
     * A scenario keeps only the student's inputs, held to the template's
     * dictionary: never the legal parameters, which are the platform's.
     *
     * @return array<string, array<string, int|float|string>>
     */
    private function scenarioInputs(mixed $inputs): array
    {
        $catalog = TemplateCatalog::fromConfig();

        return $catalog->inputs($catalog->professionalDictionary(), $inputs, 'inputs');
    }
}
