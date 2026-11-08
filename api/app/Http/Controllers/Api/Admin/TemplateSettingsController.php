<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LegalParameter;
use App\Models\LegalParameterChange;
use App\Models\LegalRegionRate;
use App\Services\FinancialsExtra;
use App\Services\LegalParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The admin's «Plantillas» page:
 * - the extra at the end of the course (the professional template): on sale
 *   or not, and its price. Off until the professional authorises the sale;
 * - the legal parameters every template calculates with: the six rates and
 *   the ITP and AJD of the 19 communities, each change kept with who made it
 *   and the value before.
 */
class TemplateSettingsController extends Controller
{
    private const COURSE = 'promocion-inmobiliaria-espana';

    public function __construct(
        private readonly FinancialsExtra $extra,
        private readonly LegalParameters $legal,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function updateExtra(Request $request): JsonResponse
    {
        $course = Course::query()->where('slug', self::COURSE)->firstOrFail();
        $module = $this->extra->module($course);
        abort_if($module === null, 404);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            // Whole euros, IVA included.
            'price_eur' => ['nullable', 'integer', 'min:1', 'max:100000', Rule::requiredIf($request->boolean('enabled'))],
        ]);

        $module->update(['price_eur' => $data['price_eur'] ?? null]);
        $this->extra->setEnabled($data['enabled']);
        AuditLog::record($request->user(), 'financials_extra.updated', $module, ['enabled' => $data['enabled'], 'price_eur' => $data['price_eur'] ?? null]);

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * A review of the legal parameters, sent whole once the admin confirmed
     * it: every value in percent, from 0 to under 100.
     */
    public function updateLegal(Request $request): JsonResponse
    {
        $codes = LegalParameter::query()->pluck('code')->all();
        $regions = LegalRegionRate::query()->pluck('name')->all();
        $percent = ['required', 'numeric', 'min:0', 'lt:100'];

        $data = $request->validate([
            'confirmed' => ['accepted'],
            'parameters' => ['required', 'array:'.implode(',', $codes)],
            'parameters.*' => $percent,
            'regions' => ['required', 'array'],
            'regions.*' => ['required', 'array:itp,ajd'],
            'regions.*.itp' => $percent,
            'regions.*.ajd' => $percent,
        ], [
            'parameters.*.min' => __('Each value must be between 0 and 100 %.'),
            'parameters.*.lt' => __('Each value must be between 0 and 100 %.'),
            'regions.*.*.min' => __('Each value must be between 0 and 100 %.'),
            'regions.*.*.lt' => __('Each value must be between 0 and 100 %.'),
        ]);

        if ($unknown = array_diff(array_keys($data['regions']), $regions)) {
            throw ValidationException::withMessages(['regions' => [__('Unknown autonomous community: :name', ['name' => implode(', ', $unknown)])]]);
        }

        $changes = $this->legal->save(
            $request->user(),
            array_map('floatval', $data['parameters']),
            array_map(fn (array $r) => array_map('floatval', $r), $data['regions']),
        );
        AuditLog::record($request->user(), 'legal_parameters.reviewed', null, ['changes' => $changes]);

        return response()->json(['data' => $this->payload()]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $course = Course::query()->where('slug', self::COURSE)->first();
        $module = $course !== null ? $this->extra->module($course) : null;

        return [
            'extra' => [
                'enabled' => $this->extra->enabled(),
                'price_eur' => $module?->price_eur,
                'on_sale' => $course !== null && $this->extra->onSale($course),
                'buyers' => $module === null ? 0 : Enrollment::query()->where('module_id', $module->id)->active()->count(),
            ],
            'legal' => [
                ...$this->legal->view(),
                'reviewed_by' => $this->legal->review()['by'],
                'history' => LegalParameterChange::query()->with('user:id,name')->latest('id')->limit(100)->get()
                    ->map(fn (LegalParameterChange $c) => [
                        'id' => $c->id,
                        'subject' => $c->subject,
                        'field' => $c->field,
                        'old_value' => $c->old_value,
                        'new_value' => $c->new_value,
                        'by' => $c->user?->name,
                        'at' => $c->created_at?->toIso8601String(),
                    ]),
            ],
        ];
    }
}
