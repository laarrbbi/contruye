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
 * awaiting a partner notary / jurist sign-off, plus the partner slots that
 * have no real contact attached yet.
 *
 * Nothing is ever rewritten or removed by this command; it only reports.
 */
class GenerateContentReview extends Command
{
    protected $signature = 'content:review {--path= : Where to write the report}';

    protected $description = 'Generate the content-review report of passages flagged "A VALIDER"';

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

        $lines[] = '# Content review — passages à valider';
        $lines[] = '';
        $lines[] = 'Rapport généré automatiquement pour la relecture par le notaire et le juriste partenaires.';
        $lines[] = 'Chaque passage ci-dessous contient une mention « à vérifier / à confirmer / à verrouiller / à valider »';
        $lines[] = 'et est affiché avec un badge **A VALIDER** côté administration (jamais côté élève).';
        $lines[] = '';
        $lines[] = 'Aucun de ces passages n\'a été supprimé ni reformulé.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($course->modules()->orderBy('order')->get() as $module) {
            /** @var Module $module */
            $moduleEntries = [];

            if (ContentValidation::flags($module->description_fr)) {
                $moduleEntries[] = ['source' => 'Description du module', 'text' => $module->description_fr];
            }

            foreach ($module->contentBlocks as $block) {
                /** @var ContentBlock $block */
                if ($block->needs_validation) {
                    $moduleEntries[] = ['source' => "Bloc [{$block->type}]", 'text' => $block->body_fr];
                }
            }

            foreach ($module->lessons()->orderBy('order')->get() as $lesson) {
                /** @var Lesson $lesson */
                foreach (ContentValidation::pending($lesson->key_points_fr ?? []) as $point) {
                    $moduleEntries[] = ['source' => "Leçon « {$lesson->title_fr} »", 'text' => $point];
                }

                if (ContentValidation::flags($lesson->content_fr)) {
                    $moduleEntries[] = ['source' => "Leçon « {$lesson->title_fr} » (contenu)", 'text' => $lesson->content_fr];
                }

                foreach ($lesson->contentBlocks as $block) {
                    if ($block->needs_validation) {
                        $moduleEntries[] = ['source' => "Leçon « {$lesson->title_fr} » — bloc [{$block->type}]", 'text' => $block->body_fr];
                    }
                }
            }

            // Partner slots with no real contact attached yet.
            $attachedCategories = $module->partners->pluck('category')->all();
            foreach ($module->contentBlocks->where('type', 'partner_slot') as $slot) {
                if (! in_array($slot->partner_category, $attachedCategories, true)) {
                    $emptySlots[] = "Module {$module->order} — {$module->title_fr} : besoin « {$slot->partner_category} »";
                }
            }

            if ($moduleEntries === []) {
                continue;
            }

            $pendingCount += count($moduleEntries);
            $lines[] = "## Module {$module->order} — {$module->title_fr}";
            $lines[] = '';

            foreach ($moduleEntries as $entry) {
                $lines[] = "- **{$entry['source']}**";
                $lines[] = '  > '.str_replace("\n", "\n  > ", trim((string) $entry['text']));
                $lines[] = '';
            }
        }

        array_splice($lines, 8, 0, [
            "**{$pendingCount} passage(s)** en attente de validation.",
            '',
        ]);

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Emplacements partenaires encore vides';
        $lines[] = '';
        $lines[] = 'Ces blocs affichent « Contact partenaire — bientôt disponible » côté élève.';
        $lines[] = 'Aucun contact n\'est inventé : ils se remplissent en rattachant un partenaire réel au module.';
        $lines[] = '';

        if ($emptySlots === []) {
            $lines[] = '_Tous les emplacements partenaires sont pourvus._';
        } else {
            foreach (array_unique($emptySlots) as $slot) {
                $lines[] = "- {$slot}";
            }
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Traduction arabe';
        $lines[] = '';
        $lines[] = 'Le contenu v2.0 est en français uniquement : les clés arabes existent mais sont vides,';
        $lines[] = 'et l\'affichage retombe sur le français en attendant une traduction humaine.';
        $lines[] = '';
        $missingAr = Module::whereNull('title_ar')->count();
        $lines[] = "- Modules sans titre arabe : {$missingAr}";
        $lines[] = '- Leçons sans titre arabe : '.Lesson::whereNull('title_ar')->count();
        $lines[] = '';

        file_put_contents($path, implode("\n", $lines)."\n");

        $this->info("Content review written to {$path}");
        $this->line("  {$pendingCount} passage(s) à valider");
        $this->line('  '.count(array_unique($emptySlots)).' emplacement(s) partenaire vide(s)');

        return self::SUCCESS;
    }
}
