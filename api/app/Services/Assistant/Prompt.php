<?php

namespace App\Services\Assistant;

use App\Support\Financials\TemplateCatalog;

/**
 * The system prompt (resources/assistant/system.md) with the assistant's
 * name, a canary and the catalogue of the course's templates (their input
 * cells, from each template's own dictionary). It is the stable, cached
 * prefix of every request: nothing in it changes from one question to the
 * next. Its version (a hash of the template, the catalogue and the name)
 * goes into every trace and into the evaluation gate: a new wording is a new
 * version, which students get only once the evaluation set passes.
 *
 * The canary is a code derived from the app key that the prompt tells the
 * model never to say: an answer containing it is an instruction leak, and
 * the output guard stops it.
 */
final class Prompt
{
    public function __construct(private readonly TemplateCatalog $catalog) {}

    public static function make(): self
    {
        return new self(TemplateCatalog::fromConfig());
    }

    /** @var array<string, string> the catalogue, read once per process from the package's dictionaries */
    private static array $catalogues = [];

    public function system(): string
    {
        return strtr(self::template(), [
            '{{name}}' => (string) config('assistant.name'),
            '{{canary}}' => self::canary(),
            '{{catalog}}' => $this->catalogue(),
        ]);
    }

    public function version(): string
    {
        return substr(hash('sha256', self::template()."\n".$this->catalogue()."\n".config('assistant.name')), 0, 16);
    }

    public static function canary(): string
    {
        return 'CNRY-'.substr(hash('sha256', 'assistant-canary|'.config('app.key')), 0, 12);
    }

    /** The calculators the assistant can run: the five small templates and the professional's «números gordos». */
    public function catalogue(): string
    {
        return self::$catalogues[(string) config('curso.path')] ??= $this->buildCatalogue();
    }

    private function buildCatalogue(): string
    {
        $keys = [...array_map('strval', array_keys($this->catalog->small())), ...array_keys(array_filter($this->catalog->professionals(), fn (array $t) => $t['template'] !== null))];
        $lines = [];
        foreach ($keys as $key) {
            $template = $this->catalog->calculator($key);
            if ($template === null) {
                continue;
            }
            $dictionary = $this->catalog->calculatorDictionary($template);
            $lines[] = "## plantilla «{$key}»: {$template['title']} (Módulo {$template['module_order']})";
            foreach ($dictionary['entradas'] as $input) {
                $default = is_scalar($input['defecto'] ?? null) ? (string) $input['defecto'] : '';
                $unit = ($input['unidad'] ?? '') !== '' ? ', '.$input['unidad'] : '';
                $lines[] = "- {$input['celda']}: {$input['etiqueta']} ({$input['tipo']}{$unit}; ejemplo {$default})";
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /** The instructions as written, before the name, the canary and the catalogue go in. */
    public static function template(): string
    {
        return (string) file_get_contents(resource_path('assistant/system.md'));
    }
}
