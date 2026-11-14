<?php

namespace App\Services\Assistant;

use App\Models\Course;
use App\Services\LegalParameters;
use App\Services\TemplateEngine;
use App\Support\Financials\TemplateCatalog;
use App\Support\Financials\XlsxFormats;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What the model may do besides writing, each with the student's rights and
 * no more: search what the student may read, calculate with a template of a
 * module the student owns (the package's engine, the legal parameters in
 * force), read the legal parameters, and, for the small model, hand a hard
 * question to the large one. Nothing here reads an account, an order, a user
 * or a setting: the model has no way to reach anyone's data.
 */
final class Tools
{
    public const SEARCH = 'buscar_en_el_curso';

    public const TEMPLATE = 'calcular_plantilla';

    public const LEGAL = 'parametros_legales';

    public const ESCALATE = 'consultar_al_experto';

    private ?TemplateCatalog $catalog = null;

    public function __construct(
        private readonly Retriever $retriever,
        private readonly TemplateEngine $engine,
        private readonly LegalParameters $legal,
    ) {}

    private function catalog(): TemplateCatalog
    {
        return $this->catalog ??= TemplateCatalog::fromConfig();
    }

    /** @return list<array<string, mixed>> */
    public function definitions(bool $canEscalate): array
    {
        $tools = [
            [
                'name' => self::SEARCH,
                'description' => 'Busca en el contenido del curso que el alumno puede leer: lecciones, textos de los módulos (leyes que aplican, dónde ir), checklists y documentos del curso. Devuelve documentos numerados a continuación de los que ya tienes. La consulta, en español y con las palabras del sector.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['consulta' => ['type' => 'string', 'description' => 'Qué buscar, en español: «nota simple», «seguro decenal», «licencia de primera ocupación»…']],
                    'required' => ['consulta'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => self::TEMPLATE,
                'description' => 'Calcula con una plantilla del curso de un módulo que el alumno tiene, con el motor de la plataforma y los parámetros legales vigentes. Devuelve la hoja calculada: cada fila con su concepto y su valor. Las casillas que no indiques conservan el ejemplo de la plantilla.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'plantilla' => ['type' => 'string', 'enum' => $this->calculatorKeys(), 'description' => 'La plantilla: «1» a «5», o «numeros_gordos_solar».'],
                        'datos' => [
                            'type' => 'array',
                            'description' => 'Las casillas de entrada que cambias, con su valor como texto: números con punto decimal, porcentajes en tanto por uno (0.21), fechas dd/mm/aaaa, SI o NO, o una opción de la lista.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'celda' => ['type' => 'string', 'description' => 'La casilla, p. ej. B5.'],
                                    'valor' => ['type' => 'string'],
                                ],
                                'required' => ['celda', 'valor'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['plantilla', 'datos'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => self::LEGAL,
                'description' => 'Los parámetros legales vigentes que aplican las plantillas: IVA general y reducido, AJD del préstamo, interés legal y, por comunidad autónoma, el ITP y el AJD. Los mantiene al día el equipo de CONSTRUYE.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false],
                'strict' => true,
            ],
        ];

        if ($canEscalate) {
            $tools[] = [
                'name' => self::ESCALATE,
                'description' => 'Pasa la pregunta a un modelo más capaz cuando pide un análisis a fondo, un cálculo de viabilidad con varios pasos o una comparación compleja. No escribas tú la respuesta si la usas.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['motivo' => ['type' => 'string', 'description' => 'Por qué la pregunta necesita al experto, en una frase.']],
                    'required' => ['motivo'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ];
        }

        return $tools;
    }

    /**
     * Search what the student may read, in Spanish and in their language.
     *
     * @param  list<int>  $exclude
     * @return list<array{id: int, title: string, path: ?string, text: string, access: string, module_id: ?int, score: float}>
     */
    public function search(string $query, string $locale, Scope $scope, array $exclude): array
    {
        return $this->retriever->search($query, $locale, $scope, (int) config('assistant.retrieval.top_k'), $exclude);
    }

    /**
     * Run a template for the student, if the student owns its module.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: string, error: bool}
     */
    public function template(array $input, Course $course, Scope $scope): array
    {
        $key = (string) ($input['plantilla'] ?? '');
        $template = $this->catalog()->calculator($key);
        if ($template === null) {
            return ['content' => "No existe la plantilla «{$key}».", 'error' => true];
        }

        $module = $course->includedModules()->where('order', $template['module_order'])->first();
        if ($module === null || ! ($scope->admin || in_array((int) $module->id, $scope->modules, true))) {
            return ['content' => "El alumno no tiene el Módulo {$template['module_order']}: esta plantilla es de ese módulo y no se puede calcular para él.", 'error' => true];
        }

        $dictionary = $this->catalog()->calculatorDictionary($template);
        $inputs = [];
        foreach ($dictionary['entradas'] as $entry) {
            $inputs[strtoupper($entry['celda'])] = $entry;
        }

        $datos = [];
        foreach ((array) ($input['datos'] ?? []) as $pair) {
            $cell = strtoupper(trim((string) ($pair['celda'] ?? '')));
            $entry = $inputs[$cell] ?? null;
            if ($entry === null) {
                return ['content' => "La casilla {$cell} no es una entrada de la plantilla «{$key}».", 'error' => true];
            }
            $value = $this->typed($entry, (string) ($pair['valor'] ?? ''));
            if ($value === null) {
                return ['content' => "El valor de {$cell} no es válido para «{$entry['etiqueta']}» ({$entry['tipo']}).", 'error' => true];
            }
            $datos[$entry['hoja']][$entry['celda']] = $value;
        }

        try {
            $clean = $this->catalog()->inputs($dictionary, $datos, 'datos');
            $result = $this->engine->small($key, $clean);
        } catch (ValidationException $e) {
            return ['content' => 'La plantilla no acepta esos datos: '.implode(' ', array_merge(...array_values($e->errors()))), 'error' => true];
        } catch (Throwable) {
            return ['content' => 'La plantilla no está disponible ahora mismo.', 'error' => true];
        }

        $cells = is_array($result['hoja']['celdas'] ?? null) ? $result['hoja']['celdas'] : [];
        // The professional's «números gordos» come without number formats: the delivered .xlsx has them.
        if ($template['number'] === null) {
            $formats = XlsxFormats::of($this->catalog()->calculatorWorkbook($template), (string) ($result['hoja']['nombre'] ?? ''));
            foreach ($cells as $address => $cell) {
                if (is_array($cell) && ($cell['nf'] ?? null) === null && isset($formats[$address])) {
                    $cells[$address]['nf'] = $formats[$address];
                }
            }
        }

        $used = $datos === [] ? 'los datos del ejemplo de la plantilla' : 'estos datos: '.implode(', ', array_map(
            fn (string $cell, $value) => "{$cell} = {$value}",
            array_keys(array_merge(...array_values($datos))),
            array_values(array_merge(...array_values($datos))),
        )).'; el resto, el ejemplo de la plantilla';

        return ['content' => "Plantilla «{$key}» · {$template['title']}, calculada con {$used}:\n".$this->sheet($cells), 'error' => false];
    }

    /** @return array{content: string, error: bool} */
    public function legalParameters(): array
    {
        $view = $this->legal->view();
        $lines = ['Parámetros legales vigentes (revisados el '.($view['reviewed_at'] ?? 'sin fecha').'):'];
        foreach ($view['parameters'] as $parameter) {
            $lines[] = '- '.$parameter['label'].': '.$this->percent((float) $parameter['value']);
        }
        $lines[] = 'Por comunidad autónoma (ITP · AJD):';
        foreach ($view['regions'] as $region) {
            $lines[] = '- '.$region['name'].': ITP '.$this->percent((float) $region['itp']).' · AJD '.$this->percent((float) $region['ajd']).($region['note'] ? ' ('.$region['note'].')' : '');
        }

        return ['content' => implode("\n", $lines), 'error' => false];
    }

    /** @return list<string> */
    private function calculatorKeys(): array
    {
        return [
            ...array_map('strval', array_keys($this->catalog()->small())),
            ...array_keys(array_filter($this->catalog()->professionals(), fn (array $t) => $t['template'] !== null)),
        ];
    }

    /**
     * The model's text as the cell's type: numbers with a decimal point (or
     * a Spanish «250.000,50»), a percentage as a fraction («21 %» is 0.21),
     * SI/NO, a list's option as the list spells it, a date as written.
     *
     * @param  array<string, mixed>  $entry
     */
    private function typed(array $entry, string $raw): int|float|string|null
    {
        $raw = trim($raw);
        $type = (string) $entry['tipo'];
        if ($type === 'numero' || $type === 'porcentaje') {
            $percent = str_contains($raw, '%');
            $number = str_replace([' ', "\u{00A0}", '€', '%'], '', $raw);
            if (str_contains($number, ',') && str_contains($number, '.')) {
                $number = str_replace(['.', ','], ['', '.'], $number);
            } elseif (str_contains($number, ',')) {
                $number = str_replace(',', '.', $number);
            } elseif ($type === 'numero' && preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+$/', $number)) {
                // «250.000»: thousands, as a Spanish amount is written.
                $number = str_replace('.', '', $number);
            }
            if (! is_numeric($number)) {
                return null;
            }
            $value = $number + 0;

            return $percent && $type === 'porcentaje' ? $value / 100 : $value;
        }
        if ($type === 'SI/NO') {
            $upper = str_replace('Í', 'I', mb_strtoupper($raw));

            return in_array($upper, ['SI', 'NO'], true) ? $upper : null;
        }
        if (str_starts_with($type, 'lista:')) {
            $options = $entry['opciones'] ?? explode('/', trim(substr($type, 6)));
            foreach ($options as $option) {
                if (mb_strtolower(Tokenizer::plain((string) $option)) === mb_strtolower(Tokenizer::plain($raw))) {
                    return (string) $option;
                }
            }

            return null;
        }

        return $raw === '' ? null : $raw;
    }

    /** @param array<string, array{v?: mixed, nf?: string}> $cells */
    private function sheet(array $cells): string
    {
        $rows = [];
        foreach ($cells as $address => $cell) {
            if (! preg_match('/^([A-Z]+)(\d+)$/', $address, $m) || (int) $m[2] < 3) {
                continue;
            }
            $value = $cell['v'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (is_float($value) || is_int($value)) {
                $value = str_contains((string) ($cell['nf'] ?? ''), '%') ? $this->percent((float) $value) : number_format((float) $value, 2, ',', '.');
            }
            $rows[(int) $m[2]][$m[1]] = (string) $value;
        }
        ksort($rows);

        $lines = [];
        foreach ($rows as $row => $values) {
            ksort($values);
            $lines[] = "{$row}: ".implode(' | ', $values);
        }

        return mb_substr(implode("\n", $lines), 0, 6000);
    }

    private function percent(float $fraction): string
    {
        return rtrim(rtrim(number_format($fraction * 100, 2, ',', '.'), '0'), ',').' %';
    }
}
