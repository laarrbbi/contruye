<?php

namespace App\Support\Financials;

use App\Models\LegalParameter;
use App\Models\LegalRegionRate;
use App\Services\LegalParameters;
use App\Services\TemplateEngine;

/**
 * Plantilla 1 · Compra del solar, as a visitor of the public pages may try it
 * (constitution III, amendment of 2026-11-12): four of its seven inputs (the
 * community, the price, who sells, the buildable floor area), the other three
 * at the template's own defaults, and three of its results. The rest — the
 * three other inputs, each tax line, the money needed on the day, the Excel —
 * stays with the owners of Module 1, where the template lives in full.
 *
 * It is the package's template, run by its engine with the legal parameters in
 * force: nothing here recomputes a formula. Labels are the template's own,
 * read from its sheet.
 */
final class SolarTeaser
{
    public const TEMPLATE = 1;

    private const SHEET = 'Calculadora';

    /** The inputs a visitor fills: name => the template's cell. */
    public const INPUTS = [
        'community' => 'B4',
        'price' => 'B5',
        'seller_company' => 'B6',
        'buildable_m2' => 'B7',
    ];

    /** The results a visitor sees: name => cell. */
    public const SHOWN = [
        'land_cost' => 'B17',
        'per_m2' => 'B19',
        'tax_rate' => 'B20',
    ];

    /** The results kept for Module 1: shown by their label, never their value. */
    public const LOCKED = ['B13', 'B14', 'B15', 'B16', 'B18'];

    /** The inputs left at the template's defaults, shown by label and value. */
    public const DEFAULTED = ['B8', 'B9', 'B10'];

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly LegalParameters $legal,
    ) {}

    /**
     * What the form needs: the template's communities and defaults, the
     * labels of what is locked, and the rates of every community.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $dictionary = $this->dictionary();
        $byCell = collect($dictionary['entradas'])->keyBy('celda');
        $sheet = $this->sheet();

        return [
            'template' => [
                'number' => self::TEMPLATE,
                'title' => $dictionary['plantilla'],
            ],
            'communities' => $byCell[self::INPUTS['community']]['opciones'],
            'defaults' => [
                'community' => $byCell[self::INPUTS['community']]['defecto'],
                'price' => $byCell[self::INPUTS['price']]['defecto'],
                'seller_company' => $byCell[self::INPUTS['seller_company']]['defecto'] === 'SI',
                'buildable_m2' => $byCell[self::INPUTS['buildable_m2']]['defecto'],
            ],
            // Notaría (a share of the price), plusvalía and due diligence, as the template sets them.
            'defaulted' => collect(self::DEFAULTED)->map(fn (string $cell) => [
                'label' => $byCell[$cell]['etiqueta'],
                'value' => $byCell[$cell]['defecto'],
                'type' => $byCell[$cell]['tipo'],
            ])->values()->all(),
            'labels' => collect(self::SHOWN)->map(fn (string $cell) => $this->label($sheet, $cell))->all(),
            'locked' => collect(self::LOCKED)->map(fn (string $cell) => $this->label($sheet, $cell))->values()->all(),
            'vat' => (float) (LegalParameter::query()->where('code', 'IVA_GENERAL')->value('value') ?? 0),
            'regions' => LegalRegionRate::query()->orderBy('order')->get()->map(fn (LegalRegionRate $rate) => [
                'name' => $rate->name,
                'itp' => $rate->itp,
                'ajd' => $rate->ajd,
                'note' => $rate->note,
            ])->all(),
            'reviewed_at' => $this->legal->review()['at'],
        ];
    }

    /**
     * Run the template on the visitor's four values and keep three results.
     *
     * @param  array{community: string, price: int|float, seller_company: bool, buildable_m2: int|float}  $values
     * @return array{land_cost: float, per_m2: float, tax_rate: float, tax: string}
     */
    public function calculate(array $values): array
    {
        $catalog = TemplateCatalog::fromConfig();
        $datos = $catalog->inputs($this->dictionary(), [self::SHEET => [
            self::INPUTS['community'] => $values['community'],
            self::INPUTS['price'] => $values['price'],
            self::INPUTS['seller_company'] => $values['seller_company'] ? 'SI' : 'NO',
            self::INPUTS['buildable_m2'] => $values['buildable_m2'],
        ]], 'datos');

        $cells = $this->engine->small(self::TEMPLATE, $datos)['hoja']['celdas'] ?? [];
        $value = fn (string $cell) => (float) ($cells[$cell]['v'] ?? 0);

        return [
            'land_cost' => $value(self::SHOWN['land_cost']),
            'per_m2' => $value(self::SHOWN['per_m2']),
            'tax_rate' => $value(self::SHOWN['tax_rate']),
            // Which tax that rate is, by the template's own rule (C6): a company sells with VAT and AJD.
            'tax' => $values['seller_company'] ? 'AJD' : 'ITP',
        ];
    }

    /** @return array<string, mixed> */
    private function dictionary(): array
    {
        return TemplateCatalog::fromConfig()->smallDictionary(self::TEMPLATE);
    }

    /** @return array<string, mixed> the template's first sheet, as the package delivers it */
    private function sheet(): array
    {
        return TemplateCatalog::fromConfig()->smallTemplate(self::TEMPLATE)['hojas'][0];
    }

    /** The label the template writes in column A of a result's row. */
    private function label(array $sheet, string $cell): string
    {
        return (string) ($sheet['celdas']['A'.substr($cell, 1)]['v'] ?? $cell);
    }
}
