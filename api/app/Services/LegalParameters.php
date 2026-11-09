<?php

namespace App\Services;

use App\Models\LegalParameter;
use App\Models\LegalParameterChange;
use App\Models\LegalRegionRate;
use App\Models\Setting;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Support\Facades\DB;

/**
 * The legal parameters every template calculates with: the same for every
 * user, set by the admin, applied before each calculation of the big
 * template or of a small one (setParametros in the engine). A user's project
 * keeps only its inputs, never these.
 */
class LegalParameters
{
    /** Decimals kept on a fraction: 0.0015 is 0,15 %. */
    private const PRECISION = 6;

    /**
     * What the engine takes: { IVA_GENERAL: 0.21, …, comunidades: { 'Cataluña': { itp, ajd } } }.
     *
     * @return array<string, mixed>
     */
    public function forEngine(): array
    {
        return [
            ...LegalParameter::query()->orderBy('order')->pluck('value', 'code')->map(fn ($v) => (float) $v)->all(),
            'comunidades' => LegalRegionRate::query()->orderBy('order')->get()
                ->mapWithKeys(fn (LegalRegionRate $r) => [$r->name => ['itp' => $r->itp, 'ajd' => $r->ajd]])
                ->all(),
        ];
    }

    /**
     * The parameters as a page shows them (fractions; the page writes the %),
     * with the date of the last review.
     *
     * @return array<string, mixed>
     */
    public function view(): array
    {
        return [
            'parameters' => LegalParameter::query()->orderBy('order')->get()->map(fn (LegalParameter $p) => [
                'code' => $p->code,
                'label' => $p->label,
                'cell' => $p->cell,
                'value' => $p->value,
                'original_value' => $p->original_value,
                'legal_basis' => $p->legal_basis,
                // The same text with its laws linked to their official texts.
                'legal_basis_html' => Markdown::inline($p->legal_basis),
                'source' => $p->source,
                'used_in' => $p->used_in,
            ])->all(),
            'regions' => LegalRegionRate::query()->orderBy('order')->get()->map(fn (LegalRegionRate $r) => [
                'name' => $r->name,
                'itp' => $r->itp,
                'ajd' => $r->ajd,
                'note' => $r->note,
                'note_html' => Markdown::inline($r->note),
            ])->all(),
            'reviewed_at' => $this->review()['at'],
        ];
    }

    /**
     * The «Parámetros legales» sheet as a workbook writes it: cell => value,
     * the cells as the package maps them.
     *
     * @param  array{parameters: array<string, string>, regions: array<string, array{itp: string, ajd: string}>}  $cells
     * @return array<string, float>
     */
    public function cellValues(array $cells): array
    {
        $values = [];
        foreach (LegalParameter::query()->get() as $parameter) {
            if (isset($cells['parameters'][$parameter->code])) {
                $values[$cells['parameters'][$parameter->code]] = $parameter->value;
            }
        }
        foreach (LegalRegionRate::query()->get() as $region) {
            if (isset($cells['regions'][$region->name])) {
                $values[$cells['regions'][$region->name]['itp']] = $region->itp;
                $values[$cells['regions'][$region->name]['ajd']] = $region->ajd;
            }
        }

        return $values;
    }

    /** @return array{at: ?string, by: ?string} */
    public function review(): array
    {
        $review = Setting::getValue('legal.review');

        return ['at' => $review['at'] ?? null, 'by' => $review['by'] ?? null];
    }

    /**
     * Save a review: the values as the admin left them (percentages), every
     * change recorded with the value before. Saving with nothing changed
     * still dates the review: the admin checked them against the law.
     *
     * @param  array<string, float|int>  $parameters  code => %
     * @param  array<string, array{itp?: float|int, ajd?: float|int}>  $regions  name => %
     * @return int the number of values changed
     */
    public function save(User $admin, array $parameters, array $regions): int
    {
        return DB::transaction(function () use ($admin, $parameters, $regions) {
            $changes = 0;

            foreach (LegalParameter::query()->lockForUpdate()->get() as $parameter) {
                if (array_key_exists($parameter->code, $parameters)) {
                    $changes += $this->apply($admin, $parameter, 'value', $parameter->code, $parameters[$parameter->code]);
                }
            }

            foreach (LegalRegionRate::query()->lockForUpdate()->get() as $region) {
                foreach (['itp', 'ajd'] as $field) {
                    if (isset($regions[$region->name]) && array_key_exists($field, $regions[$region->name])) {
                        $changes += $this->apply($admin, $region, $field, $region->name, $regions[$region->name][$field]);
                    }
                }
            }

            Setting::setValue('legal.review', ['at' => now()->toIso8601String(), 'by' => $admin->name]);

            return $changes;
        });
    }

    private function apply(User $admin, LegalParameter|LegalRegionRate $model, string $field, string $subject, float|int $percent): int
    {
        $old = round((float) $model->{$field}, self::PRECISION);
        $new = round($percent / 100, self::PRECISION);

        if ($old === $new) {
            return 0;
        }

        LegalParameterChange::query()->create([
            'user_id' => $admin->id,
            'subject' => $subject,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
        $model->update([$field => $new]);

        return 1;
    }
}
