<?php

namespace App\Console\Commands;

use App\Services\Assistant\Indexer;
use Illuminate\Console\Command;

/**
 * Rebuilds Nicolás's index of the course from the database: lessons, module
 * texts, checklists, documents, the offices of each province (Anexo A) and
 * the glossary, in every language; the templates' cards and the websites each
 * lesson names, in Spanish; each piece with who may read it. Run after a new
 * package or translations are seeded; deploy.sh runs it on every deploy, and
 * `assistant:retrieval` then checks the search on it (no model). A new index
 * is a new version: students get it once `assistant:eval` has passed for it.
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
