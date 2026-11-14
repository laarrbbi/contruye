<?php

namespace App\Console\Commands;

use App\Services\Assistant\Indexer;
use Illuminate\Console\Command;

/**
 * Rebuilds Nicolás's index of the course from the database (lessons, module
 * texts, checklists, documents, in every language), each piece with who may
 * read it. Run after a new package or translations are seeded; deploy.sh
 * runs it on every deploy. A new index is a new version: students get it
 * once `assistant:eval` has passed for it.
 */
class AssistantIndex extends Command
{
    protected $signature = 'assistant:index';

    protected $description = 'Rebuild the AI assistant\'s index of the course';

    public function handle(Indexer $indexer): int
    {
        $state = $indexer->rebuild();
        $this->info("Index {$state['version']}: {$state['chunks']} pieces of the course.");

        return self::SUCCESS;
    }
}
