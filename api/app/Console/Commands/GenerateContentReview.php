<?php

namespace App\Console\Commands;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Support\ContentValidation;
use Illuminate\Console\Command;

/**
 * Produces `content-review.md` — the list of every course passage still
 * awaiting the instructor's (or a partner notary / lawyer's) sign-off, plus
 * the partner slots that have no real contact attached yet.
 *
 * Nothing is ever rewritten or removed by this command; it only reports.
 */
class GenerateContentReview extends Command
{
    protected $signature = 'content:review {--path= : Where to write the report}';

    protected $description = 'Generate the content-review report of passages flagged "A VALIDAR"';

    public function handle(): int
    {
        $course = Course::query()->with(['modules.lessons', 'modules.contentBlocks', 'modules.partners'])->first();

        if ($course === null) {
            $this->error('No course found — seed the curriculum first.');

            return self::FAILURE;
        }

        // base_path() is the mounted /api directory, so the report lands in the repo.
        $path = $this->option('path') ?: base_path('content-review.md');
        $lines = [];
        $pendingCount = 0;
        $emptySlots = [];

        $lines[] = '# Revisión de contenido — pasajes por validar';
        $lines[] = '';
        $lines[] = 'Informe generado automáticamente para la revisión del formador y de los profesionales colaboradores (notario, abogado).';
        $lines[] = 'Cada pasaje contiene una mención «pendiente de validar / verificar / confirmar» o «por validar / verificar / confirmar»';
        $lines[] = 'y se muestra con una etiqueta **A VALIDAR** en la administración (nunca al alumno).';
        $lines[] = '';
        $lines[] = 'Ninguno de estos pasajes se ha eliminado ni reformulado.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($course->modules()->orderBy('order')->get() as $module) {
            /** @var Module $module */
            $moduleEntries = [];

            if (ContentValidation::flags($module->description_es)) {
                $moduleEntries[] = ['source' => 'Descripción de la fase', 'text' => $module->description_es];
            }

            foreach ($module->contentBlocks as $block) {
                /** @var ContentBlock $block */
                if ($block->needs_validation) {
                    $moduleEntries[] = ['source' => "Bloque [{$block->type}]", 'text' => $block->body_es];
                }
            }

            foreach ($module->lessons()->orderBy('order')->get() as $lesson) {
                /** @var Lesson $lesson */
                foreach (ContentValidation::pending($lesson->key_points_es ?? []) as $point) {
                    $moduleEntries[] = ['source' => "Lección «{$lesson->title_es}»", 'text' => $point];
                }

                if (ContentValidation::flags($lesson->content_es)) {
                    $moduleEntries[] = ['source' => "Lección «{$lesson->title_es}» (contenido)", 'text' => $lesson->content_es];
                }

                foreach ($lesson->contentBlocks as $block) {
                    if ($block->needs_validation) {
                        $moduleEntries[] = ['source' => "Lección «{$lesson->title_es}» — bloque [{$block->type}]", 'text' => $block->body_es];
                    }
                }
            }

            // Partner slots with no real contact attached yet.
            $attachedCategories = $module->partners->pluck('category')->all();
            foreach ($module->contentBlocks->where('type', 'partner_slot') as $slot) {
                if (! in_array($slot->partner_category, $attachedCategories, true)) {
                    $emptySlots[] = "Fase {$module->order} — {$module->title_es}: se necesita «{$slot->partner_category}»";
                }
            }

            if ($moduleEntries === []) {
                continue;
            }

            $pendingCount += count($moduleEntries);
            $lines[] = "## Fase {$module->order} — {$module->title_es}";
            $lines[] = '';

            foreach ($moduleEntries as $entry) {
                $lines[] = "- **{$entry['source']}**";
                $lines[] = '  > '.str_replace("\n", "\n  > ", trim((string) $entry['text']));
                $lines[] = '';
            }
        }

        array_splice($lines, 8, 0, [
            "**{$pendingCount} pasaje(s)** pendientes de validación.",
            '',
        ]);

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Huecos de colaboradores todavía vacíos';
        $lines[] = '';
        $lines[] = 'Estos bloques muestran «Contacto colaborador — disponible próximamente» al alumno.';
        $lines[] = 'No se inventa ningún contacto: se rellenan vinculando un colaborador real a la fase.';
        $lines[] = '';

        if ($emptySlots === []) {
            $lines[] = '_Todos los huecos de colaboradores están cubiertos._';
        } else {
            foreach (array_unique($emptySlots) as $slot) {
                $lines[] = "- {$slot}";
            }
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Traducción al inglés';
        $lines[] = '';
        $lines[] = 'El contenido está redactado solo en español: los campos en inglés existen pero están vacíos,';
        $lines[] = 'y la interfaz en inglés muestra el texto en español hasta que exista una traducción humana.';
        $lines[] = '';
        $missingEn = Module::whereNull('title_en')->count();
        $lines[] = "- Fases sin título en inglés: {$missingEn}";
        $lines[] = '- Lecciones sin título en inglés: '.Lesson::whereNull('title_en')->count();
        $lines[] = '';

        file_put_contents($path, implode("\n", $lines)."\n");

        $this->info("Content review written to {$path}");
        $this->line("  {$pendingCount} pasaje(s) por validar");
        $this->line('  '.count(array_unique($emptySlots)).' hueco(s) de colaborador vacío(s)');

        return self::SUCCESS;
    }
}
