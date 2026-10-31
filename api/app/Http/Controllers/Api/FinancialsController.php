<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\FinancialScenario;
use App\Models\Module;
use App\Models\User;
use App\Support\Course\CourseSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Financials: the professional's template, calculated on the server.
 *
 * The form is built from the template's own dictionary of input cells
 * (entradas.json), and a calculation is the template itself, run by the
 * financials service with its original formulas: the API only checks who is
 * asking and passes the inputs through. The service refuses any cell that is
 * not an input, so no formula can be overwritten from here.
 *
 * Everything here is for the owners of the Financials module.
 */
class FinancialsController extends Controller
{
    use AuthorizesCourseAccess;

    /** Sheets whose cells a student may fill (the template's own rule). */
    private const INPUT_SHEETS = ['Datos de Partida', '3ª Proyectos', '3ª Pólizas de Crédito'];

    private const MAX_SCENARIOS = 50;

    public function inputs(Request $request, Course $course): JsonResponse
    {
        $this->authorizeFinancials($request->user(), $course);

        $dictionary = CourseSource::fromConfig()->json('2_plantilla_financiera/plantilla/entradas.json');

        return response()->json(['data' => $dictionary]);
    }

    public function calculate(Request $request, Course $course): JsonResponse
    {
        $this->authorizeFinancials($request->user(), $course);

        $validated = $request->validate([
            'datos' => ['present', 'array'],
            'hojas' => ['sometimes', 'array', 'max:2'],
            'hojas.*' => ['string', 'max:60'],
        ]);
        $datos = $this->validInputs($validated['datos'], 'datos');

        try {
            $response = Http::timeout(config('services.plantilla.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.plantilla.url'), '/').'/calcular', [
                    // An empty object, not a list, when nothing is filled.
                    'datos' => (object) $datos,
                    'hojas' => array_values($validated['hojas'] ?? []),
                ]);
        } catch (ConnectionException $e) {
            Log::error('Financials service unreachable', ['error' => $e->getMessage()]);

            return response()->json(['message' => __('The financial template is not available at the moment. Please try again later.')], 503);
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages(['datos' => [(string) $response->json('error')]]);
        }

        if (! $response->successful()) {
            Log::error('Financials service failed', ['status' => $response->status()]);

            return response()->json(['message' => __('The financial template is not available at the moment. Please try again later.')], 503);
        }

        return response()->json(['data' => $response->json()]);
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
            'inputs' => $this->validInputs($data['inputs'], 'inputs'),
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

        $scenario->update(['name' => $data['name'], 'inputs' => $this->validInputs($data['inputs'], 'inputs')]);

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
     * The inputs as { sheet: { cell: value } }, on the template's input sheets,
     * with scalar values. Which cells are inputs is the service's call: it
     * holds the template's dictionary and refuses anything else.
     *
     * @param  array<mixed>  $inputs
     * @return array<string, array<string, int|float|string|null>>
     */
    private function validInputs(array $inputs, string $field): array
    {
        $clean = [];
        $count = 0;

        foreach ($inputs as $sheet => $cells) {
            if (! in_array($sheet, self::INPUT_SHEETS, true) || ! is_array($cells)) {
                throw ValidationException::withMessages([$field => [__('The inputs do not match the financial template.')]]);
            }

            foreach ($cells as $cell => $value) {
                $count++;
                $valid = is_string($cell) && preg_match('/^[A-Z]{1,3}[1-9]\d{0,3}$/', $cell) === 1
                    && ($value === null || is_int($value) || is_float($value) || (is_string($value) && mb_strlen($value) <= 120));

                if (! $valid || $count > 400) {
                    throw ValidationException::withMessages([$field => [__('The inputs do not match the financial template.')]]);
                }

                $clean[$sheet][$cell] = $value;
            }
        }

        return $clean;
    }
}
