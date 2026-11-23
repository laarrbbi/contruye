<?php

namespace App\Services\Assistant;

use App\Support\Financials\TemplateCatalog;

/**
 * The course's templates as text for the assistant's index, in Spanish: the
 * templates are the instructor's and stay in Spanish in every language (the
 * calculators show them so), and the retriever always reads Spanish too.
 *
 * For each template: what it is for, every input cell with its label, type
 * and the template's own example, every result with its formula and the
 * cells that formula reads, and the course's running example (Nicolás, in
 * config/assistant_examples.php) with the results the lessons quote. Built
 * from the package (its dictionaries and sheets): a new package brings new
 * cards, never a hand-written copy that drifts from the template.
 */
final class TemplateKnowledge
{
    private ?TemplateCatalog $catalog = null;

    /** Read when it is used, from the package the config names (tests point it elsewhere). */
    private function catalog(): TemplateCatalog
    {
        return $this->catalog ??= TemplateCatalog::fromConfig();
    }

    /**
     * One card per template of the package: its key, the module (by order) whose owners may read it, its title and text;
     * the professional template (the extra) goes to the Financials module (`financials`).
     *
     * @return list<array{key: string, module_order: int|null, financials?: bool, title: string, text: string}>
     */
    public function cards(): array
    {
        $cards = [];
        foreach (array_keys($this->catalog()->small()) as $number) {
            $calculator = $this->catalog()->calculator((string) $number);
            if ($calculator !== null) {
                $cards[] = $this->calculatorCard((string) $number, $calculator, null);
            }
        }
        foreach ($this->catalog()->professionals() as $id => $professional) {
            $calculator = $professional['template'] === null ? null : $this->catalog()->calculator($id);
            $cards[] = $calculator === null
                ? [
                    'key' => $id,
                    'module_order' => $professional['module_order'],
                    'title' => $professional['title'],
                    'text' => "**{$professional['title']}** (Módulo {$professional['module_order']}, Lección {$professional['lesson']}). "
                        .rtrim($professional['use'], '.').'. Se descarga en Excel en la página «Plantilla» del módulo.',
                ]
                : $this->calculatorCard($id, $calculator, $professional);
        }
        $cards[] = $this->professionalTemplateCard();

        return $cards;
    }

    /**
     * The professional template, the Financials extra: what it calculates, its
     * inputs by section (the package's dictionary), what it gives back, and the
     * package's own example with the summary the engine gave
     * (config/assistant_examples.php). For the extra's owners: no module order,
     * the Financials module's gate.
     *
     * @return array{key: string, module_order: null, financials: true, title: string, text: string}
     */
    private function professionalTemplateCard(): array
    {
        $dictionary = $this->catalog()->professionalDictionary();
        $inputs = [];
        foreach ($dictionary['entradas'] ?? [] as $input) {
            $inputs[$input['hoja'].'!'.strtoupper((string) $input['celda'])] = $input;
        }

        $parts = ['**Plantilla profesional (extra)** (Financials). El modelo financiero completo del profesional, corregido: '
            .'el coste de la promoción por partidas, su financiación y su calendario mes a mes. Es el extra del curso: la tiene quien lo compró. '
            .'Se rellena y se calcula en la página Financials, con los parámetros legales vigentes, y se descarga en Excel; '
            .'la herramienta de cálculo del asistente no la calcula. '.trim((string) ($dictionary['regla'] ?? ''))];

        $sections = [];
        foreach ($inputs as $input) {
            $sections[(string) $input['seccion']][] = $input;
        }
        foreach ($sections as $section => $list) {
            $sheets = implode('», «', array_values(array_unique(array_map(fn (array $input) => (string) $input['hoja'], $list))));
            $lines = ["**Entradas · {$section}** (hoja «{$sheets}»):"];
            foreach ($list as $input) {
                $lines[] = '- '.$this->professionalInput($input);
            }
            $parts[] = implode("\n", $lines);
        }

        $parts[] = '**Lo que devuelve:** el coste de la promoción por partidas (terreno, construcción, urbanización, proyectos, licencias, varios, '
            .'gestión, gastos del préstamo hipotecario y de la financiación externa), cada una neta, con IVA, su IVA y su % del total; el préstamo '
            .'hipotecario; el coeficiente de paso (el coste total entre la suma de suelo, construcción y urbanización netos de gastos); la hoja '
            .'«3ª Coste Total Promoción» en su vista simplificada; y el calendario mes a mes, con el aviso de Datos de Partida E79 junto al mes cero '
            .'cuando un pago cae fuera de los 48 meses del flujo de caja.';

        $example = (array) config('assistant_examples.professional', []);
        if ($example !== []) {
            $given = [];
            foreach ($this->catalog()->professionalExample() as $sheet => $cells) {
                foreach ((array) $cells as $cell => $value) {
                    $input = $inputs[$sheet.'!'.strtoupper((string) $cell)] ?? null;
                    $given[] = $cell.' «'.($input['etiqueta'] ?? $cell).'» = '.$this->professionalValue($value, $input);
                }
            }
            $lines = ['**'.$example['label'].'** (el que carga la calculadora de Financials con «Cargar el ejemplo de 24 viviendas»). '
                .'Lo que mete: '.implode('; ', $given).'. Las demás casillas conservan el valor de la plantilla.',
                'Lo que le sale (neto · con IVA · IVA · % del total):'];
            foreach ((array) $example['results'] as $concept => [$net, $gross, $vat, $share]) {
                $lines[] = "- {$concept}: {$net} · {$gross} · {$vat} · {$share}";
            }
            $lines[] = "Préstamo hipotecario: {$example['loan']}. Coeficiente de paso: {$example['coefficient']}. ".($example['note'] ?? '');
            $parts[] = implode("\n", $lines);
        }

        return ['key' => 'profesional', 'module_order' => null, 'financials' => true, 'title' => 'Plantilla profesional (extra)', 'text' => implode("\n\n", $parts)];
    }

    /** One input of the professional template: cell, label, type and unit, the template's value, its note. */
    private function professionalInput(array $input): string
    {
        $type = trim((string) $input['tipo']);
        $kind = str_starts_with($type, 'lista:') ? 'lista: '.str_replace('/', ' / ', trim(substr($type, 6))) : $type;
        $about = $kind.(trim((string) $input['unidad']) === '' ? '' : ', '.trim((string) $input['unidad']));
        $default = $input['defecto'] ?? null;
        if (($input['defecto_es_formula'] ?? false) === true) {
            $about .= '; si no se rellena, la calcula la plantilla';
        } elseif ($default !== null && $default !== '' && $default !== 0 && $default !== 0.0) {
            $about .= '; valor de la plantilla: '.$this->professionalValue($default, $input);
        }
        $note = trim((string) ($input['nota'] ?? ''));

        return strtoupper((string) $input['celda']).' · '.trim((string) $input['etiqueta'])." ({$about})".($note === '' ? '' : '. '.rtrim($note, '.'));
    }

    /** A value of the professional template as the course writes it: a date dd/mm/aaaa, a percentage, a number. */
    private function professionalValue(mixed $value, ?array $input): string
    {
        $type = (string) ($input['tipo'] ?? '');
        if ($type === 'fecha' && is_numeric($value)) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('d/m/Y');
        }

        return $this->value($value, $type);
    }

    /**
     * @param  array{key: string, number: int|null, title: string, module_order: int, excel: string, template: string, inputs: string}  $calculator
     * @param  array<string, mixed>|null  $professional
     * @return array{key: string, module_order: int, title: string, text: string}
     */
    private function calculatorCard(string $key, array $calculator, ?array $professional): array
    {
        $sheets = [];
        foreach ($this->catalog()->calculatorTemplate($calculator)['hojas'] ?? [] as $sheet) {
            $sheets[(string) $sheet['nombre']] = (array) ($sheet['celdas'] ?? []);
        }
        $main = (string) array_key_first($sheets);
        $inputs = [];
        foreach ($this->catalog()->calculatorDictionary($calculator)['entradas'] ?? [] as $entry) {
            $inputs[strtoupper((string) $entry['celda'])] = $entry;
        }

        $parts = [];
        // A small template says on its second line which module it is and how to fill it:
        // «Módulo 2 · Análisis de viabilidad · Incluida en el curso CONSTRUYE. Rellena…».
        $where = "Módulo {$calculator['module_order']}";
        $about = rtrim((string) ($professional['use'] ?? ''), '.').($professional !== null ? ' (Lección '.$professional['lesson'].').' : '');
        if ($professional === null) {
            $line = explode(' · ', $this->text($sheets[$main]['A2']['v'] ?? null), 3);
            [$where, $about] = count($line) === 3 ? [$line[0].' · '.$line[1], $line[2]] : [$where, implode(' · ', $line)];
        }
        $parts[] = "**{$calculator['title']}** ({$where}). ".trim($about)
            .' Se calcula en la página «Plantilla» del módulo, con los parámetros legales vigentes, y se descarga en Excel.';

        $lines = ['**Entradas** (las casillas que rellena el alumno; las que no cambia conservan el ejemplo de la plantilla):'];
        foreach ($inputs as $cell => $entry) {
            $unit = trim((string) ($entry['unidad'] ?? ''));
            $default = $this->value($entry['defecto'] ?? null, (string) ($entry['tipo'] ?? ''));
            $note = trim((string) ($entry['nota'] ?? ''));
            $lines[] = "- {$cell} · {$entry['etiqueta']} ({$entry['tipo']}".($unit !== '' ? ", {$unit}" : '').($default !== '' ? "; ejemplo de la plantilla: {$default}" : '').')'
                .($note !== '' && $note !== 'Ejemplo del profesional' ? ". {$note}" : '');
        }
        $parts[] = implode("\n", $lines);

        $parts[] = implode("\n", ['**Resultados y cómo se calculan:**', ...$this->formulas($sheets, $main, $inputs)]);

        $example = config('assistant_examples.templates.'.$key);
        if (is_array($example)) {
            $parts[] = $this->example($example, $sheets, $main, $inputs);
        }

        return ['key' => $key, 'module_order' => $calculator['module_order'], 'title' => $calculator['title'], 'text' => implode("\n\n", $parts)];
    }

    /**
     * The sheet's formulas, in reading order, each with the label of its cell
     * and of the cells it reads. A column of a table with the same formula on
     * every row is told once, for the whole column.
     *
     * @param  array<string, array<string, mixed>>  $sheets
     * @param  array<string, array<string, mixed>>  $inputs
     * @return list<string>
     */
    private function formulas(array $sheets, string $main, array $inputs): array
    {
        $cells = $sheets[$main];
        $formulas = [];
        foreach ($cells as $address => $cell) {
            if (is_array($cell) && isset($cell['f']) && is_string($cell['f'])) {
                $formulas[strtoupper($address)] = ltrim($cell['f'], '=');
            }
        }
        uksort($formulas, fn (string $a, string $b) => $this->order($a) <=> $this->order($b));

        // Columns of a table that repeat one formula row after row.
        $byColumn = [];
        foreach ($formulas as $address => $formula) {
            [$column, $row] = $this->split($address);
            $byColumn[$column][$row] = preg_replace('/(?<![A-Z$])(\$?[A-Z]{1,3}\$?)'.$row.'(?!\d)/', '$1{r}', $formula);
        }
        $grouped = [];
        foreach ($byColumn as $column => $rows) {
            ksort($rows);
            $run = [];
            $previous = null;
            foreach ($rows as $row => $pattern) {
                if ($previous !== null && $row === $previous + 1 && $pattern === $rows[$previous] && str_contains($pattern, '{r}')) {
                    $run[] = $row;
                } else {
                    $this->closeRun($grouped, $column, $run);
                    $run = [$row];
                }
                $previous = $row;
            }
            $this->closeRun($grouped, $column, $run);
        }

        $lines = [];
        $done = [];
        foreach ($formulas as $address => $formula) {
            if (isset($done[$address])) {
                continue;
            }
            [$column, $row] = $this->split($address);
            $range = $grouped[$address] ?? null;
            if ($range !== null) {
                foreach (range($range[0], $range[1]) as $r) {
                    $done[$column.$r] = true;
                }
                $header = $this->header($cells, $column, $row) ?? $this->label($sheets, $main, $address, $inputs);
                $pattern = $byColumn[$column][$row];
                $reads = $this->reads($sheets, $main, str_replace('{r}', (string) $row, $pattern), $inputs, true);
                $lines[] = "- {$column}{$range[0]}:{$column}{$range[1]} · {$header}, en cada fila = ".str_replace('{r}', '', $pattern)
                    .($reads !== '' ? ", con {$reads}" : '').'.';

                continue;
            }
            $done[$address] = true;
            $label = $this->label($sheets, $main, $address, $inputs);
            $note = $this->text($cells[$column.$row]['nota'] ?? null) ?: $this->text($cells[$this->next($column).$row]['v'] ?? null);
            $reads = $this->reads($sheets, $main, $formula, $inputs, false);
            $lines[] = "- {$address} · {$label} = {$formula}".($reads !== '' ? ", con {$reads}" : '').($note !== '' ? ". {$note}" : '.');
        }

        return $lines;
    }

    /** @param array<string, array{0: int, 1: int}> $grouped @param list<int> $run */
    private function closeRun(array &$grouped, string $column, array $run): void
    {
        if (count($run) >= 3) {
            $grouped[$column.$run[0]] = [$run[0], end($run)];
        }
    }

    /**
     * The cells a formula reads, each with its label: «B6 = «Edificabilidad…»».
     *
     * @param  array<string, array<string, mixed>>  $sheets
     * @param  array<string, array<string, mixed>>  $inputs
     */
    private function reads(array $sheets, string $main, string $formula, array $inputs, bool $columnsOnly): string
    {
        preg_match_all("/(?:'([^']+)'!)?\\$?([A-Z]{1,3})\\$?(\\d+)(?::\\$?([A-Z]{1,3})\\$?(\\d+))?/", $formula, $matches, PREG_SET_ORDER);
        $seen = [];
        $parts = [];
        foreach ($matches as $m) {
            $sheet = $m[1] !== '' ? $m[1] : $main;
            if (! isset($sheets[$sheet])) {
                continue;
            }
            $from = $m[2].$m[3];
            $where = $sheet === $main ? '' : " de «{$sheet}»";
            if (($m[4] ?? '') !== '') {
                $to = $m[4].$m[5];
                $first = $this->label($sheets, $sheet, $from, $inputs);
                $last = $this->label($sheets, $sheet, $to, $inputs);
                $text = "{$from}:{$to}{$where} = de «{$first}» a «{$last}»";
            } else {
                $label = $columnsOnly ? ($this->header($sheets[$sheet], $m[2], (int) $m[3]) ?? $this->label($sheets, $sheet, $from, $inputs)) : $this->label($sheets, $sheet, $from, $inputs);
                $text = ($columnsOnly && $sheet === $main ? $m[2] : $from)."{$where} = «{$label}»";
            }
            if (! isset($seen[$text])) {
                $seen[$text] = true;
                $parts[] = $text;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * A cell's label: the input's own label, else the nearest text to its left
     * in its row, with its column's header when it sits in a table.
     *
     * @param  array<string, array<string, mixed>>  $sheets
     * @param  array<string, array<string, mixed>>  $inputs
     */
    private function label(array $sheets, string $sheet, string $address, array $inputs): string
    {
        if ($sheet === array_key_first($sheets) && isset($inputs[$address])) {
            return (string) $inputs[$address]['etiqueta'];
        }
        $cells = $sheets[$sheet] ?? [];
        [$column, $row] = $this->split($address);
        $left = '';
        for ($c = $this->index($column) - 1; $c >= 1; $c--) {
            $text = $this->text($cells[$this->letters($c).$row]['v'] ?? null);
            if ($text !== '' && ! isset($cells[$this->letters($c).$row]['f'])) {
                $left = $text;
                break;
            }
        }
        $header = $this->header($cells, $column, $row);

        return trim($left.($header !== null && $header !== $left ? ' · '.$header : '')) ?: $address;
    }

    /**
     * The column's header when the cell sits in a table: a row above it, in
     * the same unbroken block of rows, with three or more cells, all of them text.
     *
     * @param  array<string, array<string, mixed>>  $cells
     */
    private function header(array $cells, string $column, int $row): ?string
    {
        $rows = [];
        foreach ($cells as $address => $cell) {
            [$c, $r] = $this->split(strtoupper($address));
            $rows[$r][$c] = $cell;
        }
        for ($r = $row - 1; $r >= 1; $r--) {
            if (! isset($rows[$r])) {
                return null;
            }
            $all = $rows[$r];
            $text = array_filter($all, fn ($cell) => is_array($cell) && ! isset($cell['f']) && is_string($cell['v'] ?? null) && trim($cell['v']) !== '');
            if (count($all) >= 3 && count($text) === count($all)) {
                $value = $this->text($all[$column]['v'] ?? null);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Nicolás's run of this template: the cells he fills, and the results the lessons quote.
     *
     * @param  array<string, mixed>  $example
     * @param  array<string, array<string, mixed>>  $sheets
     * @param  array<string, array<string, mixed>>  $inputs
     */
    private function example(array $example, array $sheets, string $main, array $inputs): string
    {
        $lessons = array_map(fn (string $l) => 'Lección '.$l, (array) ($example['lessons'] ?? []));
        $show = fn (array $values) => implode('; ', array_map(
            fn (string $cell, $value) => $cell.' «'.$this->label($sheets, $main, $cell, $inputs).'» = '.$this->value($value, (string) ($inputs[$cell]['tipo'] ?? '')),
            array_keys($values), array_values($values),
        ));
        $results = fn (array $values) => implode('; ', array_map(
            fn (string $cell, string $value) => $cell.' «'.$this->label($sheets, $main, $cell, $inputs).'» = '.$value,
            array_keys($values), array_values($values),
        ));

        $lines = [
            '**El ejemplo del curso: Nicolás, el promotor** ('.implode(', ', $lessons).'). Lo que mete en la plantilla: '.$show((array) $example['inputs']).'.',
            'Lo que le sale, como lo cuentan las lecciones: '.$results((array) $example['results']).'. '.config('assistant_examples.note'),
        ];
        foreach ((array) ($example['variants'] ?? []) as $variant) {
            $lines[] = '- '.$variant['label'].': '.$show((array) $variant['inputs']).' → '.$results((array) $variant['results']).'.';
        }

        return implode("\n", $lines);
    }

    private function value(mixed $value, string $type): string
    {
        if (is_bool($value) || $value === null) {
            return '';
        }
        if (! is_int($value) && ! is_float($value)) {
            return trim((string) $value);
        }
        if (str_contains($type, 'porcentaje')) {
            return rtrim(rtrim(number_format($value * 100, 3, ',', '.'), '0'), ',').' %';
        }

        return $this->number($value);
    }

    /** A number as the course writes it: 1.400 · 1.586,49 · 2.606,50 · 2.476,175. */
    private function number(float|int $value): string
    {
        $value = round((float) $value, 3);
        $decimals = fmod($value, 1.0) == 0.0 ? 0 : (fmod(round($value * 1000), 10.0) == 0.0 ? 2 : 3);

        return number_format($value, $decimals, ',', '.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim(preg_replace('/\s+/', ' ', $value)) : '';
    }

    /** @return array{0: string, 1: int} */
    private function split(string $address): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($address), $m);

        return [$m[1] ?? 'A', (int) ($m[2] ?? 0)];
    }

    private function order(string $address): int
    {
        [$column, $row] = $this->split($address);

        return $row * 1000 + $this->index($column);
    }

    private function index(string $column): int
    {
        $n = 0;
        foreach (str_split($column) as $char) {
            $n = $n * 26 + (ord($char) - 64);
        }

        return $n;
    }

    private function letters(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private function next(string $column): string
    {
        return $this->letters($this->index($column) + 1);
    }
}
