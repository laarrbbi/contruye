<?php

namespace App\Console\Commands;

use App\Support\Course\CourseTranslation;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Reads every course translation (traducciones/) against the Spanish without
 * writing anything: deploy.sh runs it before maintenance mode, so a file that
 * lost a figure stops the deploy while the site is still up.
 */
class CheckTranslations extends Command
{
    protected $signature = 'app:check-translations';

    protected $description = 'Check the course translations against the Spanish (figures, laws, structure)';

    public function handle(): int
    {
        $failed = false;

        foreach (CourseTranslation::available() as $locale) {
            $translation = CourseTranslation::fromConfig($locale);

            try {
                $modules = $translation->modules();
                $translation->documents();
                $assessments = $translation->assessments();
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());
                $failed = true;

                continue;
            }

            $lessons = array_sum(array_map(
                fn (array $module) => count(array_filter(array_column($module['lessons'], 'content'))),
                $modules,
            ));
            $this->components->twoColumnDetail($locale, sprintf(
                '%d modules, %d lessons in full, %d quizzes%s',
                count($modules), $lessons, count($assessments['quizzes']), $assessments['exam'] ? ', the exam' : '',
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
