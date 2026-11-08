<?php

namespace App\Support\Financials;

use App\Support\Course\CourseSource;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

/**
 * The course's calculation templates, as the package describes them:
 * - the professional template (plantilla/, the Financials extra);
 * - the five small ones included in the course (plantillas_pequenas/), each
 *   in its module: Plantilla 1 in module 1, 2 in 2, 3 in 5, 4 in 7, 5 in 8.
 *
 * Each has a dictionary of input cells (entradas.json): the only cells a user
 * writes, with their type. inputs() holds a user's values to it.
 */
final class TemplateCatalog
{
    private const BASE = '2_plantilla_financiera';

    private const MAX_CELLS = 400;

    public function __construct(private readonly CourseSource $source) {}

    public static function fromConfig(): self
    {
        return new self(CourseSource::fromConfig());
    }

    /** @return array<string, mixed> */
    public function professionalDictionary(): array
    {
        return $this->source->json(self::BASE.'/plantilla/entradas.json');
    }

    public function professionalWorkbook(): string
    {
        return $this->source->path(self::BASE.'/excel_corregido/PLANTILLA_LIBRE_corregida.xlsx');
    }

    /** @return array<string, mixed> */
    public function professionalExample(): array
    {
        return $this->source->json(self::BASE.'/pruebas/escenario_24_viviendas.json');
    }

    /**
     * The five small templates, by number: title, the module (by its order) it belongs to, its files.
     *
     * @return array<int, array{number: int, title: string, module_order: int, module_label: string, excel: string, inputs: string}>
     */
    public function small(): array
    {
        $templates = [];
        foreach ($this->source->json(self::BASE.'/plantillas_pequenas/indice.json')['plantillas'] as $entry) {
            preg_match('/Módulo (\d+)/u', $entry['modulo'], $module);
            $templates[(int) $entry['numero']] = [
                'number' => (int) $entry['numero'],
                'title' => $entry['titulo'],
                'module_order' => (int) $module[1],
                'module_label' => $entry['modulo'],
                'excel' => $entry['excel'],
                'inputs' => $entry['entradas'],
            ];
        }

        return $templates;
    }

    /** @return array<string, mixed> */
    public function smallDictionary(int $number): array
    {
        return $this->source->json(self::BASE.'/plantillas_pequenas/'.$this->small()[$number]['inputs']);
    }

    public function smallWorkbook(int $number): string
    {
        return $this->source->path(self::BASE.'/plantillas_pequenas/'.$this->small()[$number]['excel']);
    }

    /**
     * The cells of the «Parámetros legales» sheet, as the package maps them:
     * parameter code => cell, and community => [itp cell, ajd cell].
     *
     * @return array{parameters: array<string, string>, regions: array<string, array{itp: string, ajd: string}>, sheet: string}
     */
    public function legalCells(): array
    {
        $legal = $this->source->json(self::BASE.'/plantilla/parametros_legales.json');

        return [
            'sheet' => $legal['hoja'],
            'parameters' => array_column($legal['parametros'], 'celda', 'codigo'),
            'regions' => array_combine(
                array_column($legal['comunidades'], 'nombre'),
                array_map(fn (array $c) => ['itp' => $c['celda_itp'], 'ajd' => $c['celda_ajd']], $legal['comunidades']),
            ),
        ];
    }

    /**
     * A user's values held to a template's dictionary: only its input cells,
     * each of its type. Empty values are dropped (the cell keeps the
     * template's default). Dates stay as written; toWorkbook() turns them into
     * Excel serials.
     *
     * @param  array<string, mixed>  $dictionary
     * @return array<string, array<string, int|float|string>>
     */
    public function inputs(array $dictionary, mixed $values, string $field): array
    {
        $fail = fn () => throw ValidationException::withMessages([$field => [__('The inputs do not match the financial template.')]]);

        if (! is_array($values)) {
            $fail();
        }

        $inputs = [];
        foreach ($dictionary['entradas'] as $input) {
            $inputs[$input['hoja']][$input['celda']] = $input;
        }

        $clean = [];
        $count = 0;
        foreach ($values as $sheet => $cells) {
            if (! is_string($sheet) || ! isset($inputs[$sheet]) || ! is_array($cells)) {
                $fail();
            }

            foreach ($cells as $cell => $value) {
                $input = is_string($cell) ? ($inputs[$sheet][$cell] ?? null) : null;
                if ($input === null || ++$count > self::MAX_CELLS) {
                    $fail();
                }
                if ($value === null || $value === '') {
                    continue;
                }
                if (! $this->fits($input, $value)) {
                    throw ValidationException::withMessages([$field => [__('The value of :cell is not valid.', ['cell' => "{$sheet}!{$cell}"])]]);
                }
                $clean[$sheet][$cell] = $value;
            }
        }

        return $clean;
    }

    /**
     * Inputs as the workbook stores them: dates as Excel serial numbers.
     *
     * @param  array<string, mixed>  $dictionary
     * @param  array<string, array<string, int|float|string>>  $values
     * @return array<string, array<string, int|float|string>>
     */
    public function toWorkbook(array $dictionary, array $values): array
    {
        $dates = [];
        foreach ($dictionary['entradas'] as $input) {
            if ($input['tipo'] === 'fecha') {
                $dates[$input['hoja']][$input['celda']] = true;
            }
        }

        foreach ($values as $sheet => $cells) {
            foreach ($cells as $cell => $value) {
                if (isset($dates[$sheet][$cell]) && is_string($value)) {
                    $values[$sheet][$cell] = self::serial($value);
                }
            }
        }

        return $values;
    }

    /** @param  array<string, mixed>  $input */
    private function fits(array $input, mixed $value): bool
    {
        $type = $input['tipo'];

        return match (true) {
            $type === 'numero', $type === 'porcentaje' => is_int($value) || (is_float($value) && is_finite($value)),
            $type === 'fecha' => (is_int($value) && $value >= 0) || (is_string($value) && self::serial($value) !== null),
            $type === 'SI/NO' => $value === 'SI' || $value === 'NO',
            str_starts_with($type, 'lista:') => is_string($value) && in_array($value, $input['opciones'] ?? explode('/', trim(substr($type, 6))), true),
            default => is_string($value) && mb_strlen($value) <= 120,
        };
    }

    /** 'YYYY-MM-DD' or 'dd/mm/yyyy' as an Excel serial (1900 system, as the engine reads it). */
    public static function serial(string $date): ?int
    {
        foreach (['!Y-m-d', '!d/m/Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $date, new DateTimeZone('UTC'));
            if ($parsed !== false && $parsed->format(str_replace('!', '', $format)) === $date) {
                $epoch = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));

                return (int) $epoch->diff($parsed)->days;
            }
        }

        return null;
    }
}
