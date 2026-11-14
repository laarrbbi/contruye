<?php

namespace App\Console\Commands;

use App\Models\AssistantTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the assistant's traces older than `assistant.trace_days` and the
 * frequent-question answers that have expired. The credits ledger is never
 * pruned: it is the record of what each student was given and spent.
 */
class AssistantPrune extends Command
{
    protected $signature = 'assistant:prune';

    protected $description = 'Remove old AI assistant traces and expired cached answers';

    public function handle(): int
    {
        $traces = AssistantTrace::query()->where('created_at', '<', now()->subDays((int) config('assistant.trace_days')))->delete();
        $cached = DB::table('assistant_answer_cache')->where('expires_at', '<', now())->delete();
        $this->info("{$traces} traces and {$cached} cached answers removed.");

        return self::SUCCESS;
    }
}
