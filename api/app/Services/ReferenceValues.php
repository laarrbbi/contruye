<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TemplateReferenceValue;
use App\Models\User;
use App\Support\Financials\TemplateCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The templates' reference values that the admin keeps up to date, beside the
 * legal parameters (financials/valores_de_referencia.json):
 *
 * - tariffs: fixed values of the professional template that the package
 *   itself says are of 2002 (OCT, ten-year insurance, guarantees, fee
 *   tables). The engine wrapper writes them into their cells, only those
 *   cells and never a formula, before each calculation; they go into the
 *   Excel download too.
 * - inputs: the value an input is presented with (the loan's interest rate,
 *   the notary's share…). The student can still change it; a calculation
 *   that leaves it empty uses it.
 *
 * A value the admin has not changed is the package's. Each change is kept.
 */
class ReferenceValues
{
    public const PROFESSIONAL = 'profesional';

    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    /** @var array<string, float>|null key => value, the admin's */
    private ?array $overrides = null;

    public function catalog(): array
    {
        return $this->catalog ??= json_decode((string) file_get_contents((string) config('services.plantilla.reference')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function key(string $template, string $sheet, string $cell): string
    {
        return "{$template}|{$sheet}|{$cell}";
    }

    /**
     * Every value the admin can set, by key: what it is, where it lives, its
     * package value and the bounds it is held to.
     *
     * @return array<string, array<string, mixed>>
     */
    public function items(): array
    {
        $items = [];
        $package = $this->packageTariffs();

        foreach ($this->catalog()['tarifas'] as $group) {
            foreach ($this->tariffCells($group) as $cell) {
                $key = self::key(self::PROFESSIONAL, $cell['hoja'], $cell['celda']);
                $items[$key] = [
                    'kind' => 'tariff', 'group' => $group['grupo'], 'template' => self::PROFESSIONAL,
                    'sheet' => $cell['hoja'], 'cell' => $cell['celda'], 'label' => $cell['etiqueta'],
                    'type' => $cell['tipo'] ?? $group['tipo'], 'max' => (float) ($cell['max'] ?? $group['max']),
                    'package' => (float) $package[$key],
                ];
            }
        }

        $catalog = TemplateCatalog::fromConfig();
        foreach ($this->catalog()['entradas'] as $group) {
            foreach ($group['celdas'] as $cell) {
                $template = (string) $cell['plantilla'];
                $dictionary = $template === self::PROFESSIONAL ? $catalog->professionalDictionary() : $catalog->smallDictionary((int) $template);
                $input = collect($dictionary['entradas'])->first(fn (array $e) => $e['hoja'] === $cell['hoja'] && $e['celda'] === $cell['celda']);
                $key = self::key($template, $cell['hoja'], $cell['celda']);
                $items[$key] = [
                    'kind' => 'input', 'group' => $group['grupo'], 'template' => $template,
                    'sheet' => $cell['hoja'], 'cell' => $cell['celda'], 'label' => $input['etiqueta'],
                    'type' => $input['tipo'] === 'porcentaje' ? 'porcentaje' : 'importe',
                    'max' => $input['tipo'] === 'porcentaje' ? 1.0 : 1.0e9,
                    'package' => (float) $input['defecto'], 'note' => $input['nota'] ?? '',
                ];
            }
        }

        return $items;
    }

    /** @return array<string, float> the admin's values, by key */
    public function overrides(): array
    {
        return $this->overrides ??= TemplateReferenceValue::query()->get()
            ->mapWithKeys(fn (TemplateReferenceValue $v) => [self::key($v->template, $v->sheet, $v->cell) => (float) $v->value])
            ->all();
    }

    /**
     * The tariffs to send with a calculation of the professional template:
     * only those the admin changed (the engine restores the others).
     *
     * @return array<string, array<string, float>>
     */
    public function tariffsForEngine(): array
    {
        $tariffs = [];
        foreach ($this->overrides() as $key => $value) {
            [$template, $sheet, $cell] = explode('|', $key);
            if ($template === self::PROFESSIONAL && $this->isTariff($key)) {
                $tariffs[$sheet][$cell] = $value;
            }
        }

        return $tariffs;
    }

    /**
     * A template's inputs with the admin's values filled in where the student
     * left them empty.
     *
     * @param  array<string, array<string, mixed>>  $inputs
     * @return array<string, array<string, mixed>>
     */
    public function withDefaults(string $template, array $inputs): array
    {
        foreach ($this->overrides() as $key => $value) {
            [$t, $sheet, $cell] = explode('|', $key);
            if ($t === $template && ! $this->isTariff($key) && ! isset($inputs[$sheet][$cell])) {
                $inputs[$sheet][$cell] = $value;
            }
        }

        return $inputs;
    }

    /**
     * A dictionary's inputs as the student sees them: each with the admin's
     * value as its default where there is one.
     *
     * @param  list<array<string, mixed>>  $entradas
     * @return list<array<string, mixed>>
     */
    public function presented(string $template, array $entradas): array
    {
        $overrides = $this->overrides();

        return array_map(function (array $input) use ($template, $overrides) {
            $key = self::key($template, $input['hoja'], $input['celda']);

            return isset($overrides[$key]) && ! ($input['defecto_es_formula'] ?? false)
                ? [...$input, 'defecto' => $overrides[$key]]
                : $input;
        }, $entradas);
    }

    /**
     * The admin page: every group with its values, the package's beside them.
     *
     * @return array<string, mixed>
     */
    public function view(): array
    {
        $items = $this->items();
        $overrides = $this->overrides();
        $value = fn (string $key) => $overrides[$key] ?? $items[$key]['package'];
        $row = fn (string $key) => [
            'key' => $key, 'label' => $items[$key]['label'], 'type' => $items[$key]['type'], 'max' => $items[$key]['max'],
            'value' => $value($key), 'package' => $items[$key]['package'], 'changed' => isset($overrides[$key]),
            'template' => $items[$key]['template'], 'cell' => $items[$key]['sheet'].'!'.$items[$key]['cell'],
            'note' => $items[$key]['note'] ?? null,
        ];

        $tariffs = collect($this->catalog()['tarifas'])->map(function (array $group) use ($row) {
            $sheet = $group['hoja'];

            return [
                'group' => $group['grupo'], 'title' => $group['titulo'], 'description' => $group['descripcion'],
                'table' => isset($group['tabla']) ? [
                    'columns' => array_column($group['tabla']['columnas'], 'etiqueta'),
                    'rows' => array_map(fn (array $fila) => [
                        'label' => $fila['etiqueta'],
                        'cells' => array_map(fn (array $col) => $row(self::key(self::PROFESSIONAL, $sheet, $col['col'].$fila['fila'])), $group['tabla']['columnas']),
                    ], $group['tabla']['filas']),
                ] : null,
                'items' => array_map(fn (array $cell) => $row(self::key(self::PROFESSIONAL, $cell['hoja'] ?? $sheet, $cell['celda'])), $group['celdas'] ?? []),
            ];
        })->values()->all();

        $inputs = collect($this->catalog()['entradas'])->map(fn (array $group) => [
            'group' => $group['grupo'], 'title' => $group['titulo'], 'description' => $group['descripcion'], 'table' => null,
            'items' => array_map(fn (array $cell) => $row(self::key((string) $cell['plantilla'], $cell['hoja'], $cell['celda'])), $group['celdas']),
        ])->values()->all();

        $review = Setting::getValue('reference.review');

        return [
            'note' => $this->catalog()['nota'],
            'tariffs' => $tariffs,
            'inputs' => $inputs,
            'reviewed_at' => $review['at'] ?? null,
            'reviewed_by' => $review['by'] ?? null,
            'history' => DB::table('template_reference_changes')
                ->leftJoin('users', 'users.id', '=', 'template_reference_changes.user_id')
                ->orderByDesc('template_reference_changes.id')->limit(50)
                ->get(['template_reference_changes.*', 'users.name as user_name'])
                ->map(fn ($c) => [
                    'key' => self::key($c->template, $c->sheet, $c->cell),
                    'label' => $items[self::key($c->template, $c->sheet, $c->cell)]['label'] ?? $c->cell,
                    'old_value' => (float) $c->old_value, 'new_value' => (float) $c->new_value,
                    'by' => $c->user_name, 'at' => $c->created_at,
                ])->all(),
        ];
    }

    /**
     * Save the admin's values. A value equal to the package's removes the
     * admin's row (the package applies again). Returns how many changed.
     *
     * @param  array<string, mixed>  $values  key => number
     */
    public function save(User $admin, array $values): int
    {
        $items = $this->items();

        foreach ($values as $key => $value) {
            $item = $items[$key] ?? null;
            if ($item === null) {
                throw ValidationException::withMessages(['values' => [__('Unknown reference value: :key', ['key' => $key])]]);
            }
            if (! is_numeric($value) || (float) $value < 0 || (float) $value > $item['max']) {
                throw ValidationException::withMessages(["values.{$key}" => [__('The value of «:label» is out of range.', ['label' => $item['label']])]]);
            }
        }

        $changes = DB::transaction(function () use ($admin, $values, $items) {
            $current = $this->overrides();
            $changes = 0;

            foreach ($values as $key => $value) {
                $item = $items[$key];
                $new = round((float) $value, 8);
                $old = $current[$key] ?? $item['package'];
                if (abs($new - $old) < 1e-9) {
                    continue;
                }

                $where = ['template' => $item['template'], 'sheet' => $item['sheet'], 'cell' => $item['cell']];
                if (abs($new - $item['package']) < 1e-9) {
                    TemplateReferenceValue::query()->where($where)->delete();
                } else {
                    TemplateReferenceValue::query()->updateOrCreate($where, ['value' => $new, 'updated_by' => $admin->id]);
                }
                DB::table('template_reference_changes')->insert([...$where, 'user_id' => $admin->id, 'old_value' => $old, 'new_value' => $new, 'created_at' => now()]);
                $changes++;
            }

            Setting::setValue('reference.review', ['at' => now()->toIso8601String(), 'by' => $admin->name]);

            return $changes;
        });

        $this->overrides = null;

        return $changes;
    }

    private function isTariff(string $key): bool
    {
        return ($this->items()[$key]['kind'] ?? null) === 'tariff';
    }

    /** @return list<array{hoja: string, celda: string, etiqueta: string, tipo?: string, max?: float}> */
    private function tariffCells(array $group): array
    {
        $cells = [];
        foreach ($group['tabla']['filas'] ?? [] as $fila) {
            foreach ($group['tabla']['columnas'] as $col) {
                $cells[] = ['hoja' => $group['hoja'], 'celda' => $col['col'].$fila['fila'], 'etiqueta' => $fila['etiqueta'].' · '.$col['etiqueta']];
            }
        }
        foreach ($group['celdas'] ?? [] as $cell) {
            $cells[] = [...$cell, 'hoja' => $cell['hoja'] ?? $group['hoja']];
        }

        return $cells;
    }

    /**
     * The tariffs' values as the package delivers them, read once from the
     * professional template (9 MB) and kept while the file does not change.
     *
     * @return array<string, float>
     */
    private function packageTariffs(): array
    {
        $path = rtrim((string) config('curso.path'), '/').'/2_plantilla_financiera/plantilla/plantilla_libre.json';

        return Cache::rememberForever('reference.package.'.md5($path.'|'.@filemtime($path)), function () use ($path) {
            $sheets = collect(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['hojas'])
                ->mapWithKeys(fn (array $h) => [$h['nombre'] => $h['celdas']]);
            $values = [];
            foreach ($this->catalog()['tarifas'] as $group) {
                foreach ($this->tariffCells($group) as $cell) {
                    $values[self::key(self::PROFESSIONAL, $cell['hoja'], $cell['celda'])] = (float) $sheets[$cell['hoja']][$cell['celda']]['v'];
                }
            }

            return $values;
        });
    }
}
