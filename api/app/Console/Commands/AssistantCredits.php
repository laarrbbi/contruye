<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Assistant\Credits;
use Illuminate\Console\Command;

/**
 * Credits for one student this month, written to the ledger (append-only):
 * a goodwill top-up, or a negative number to correct one.
 *
 *   php artisan assistant:credits ana@example.es 50 --note="Problema del 12/11"
 */
class AssistantCredits extends Command
{
    protected $signature = 'assistant:credits {email} {credits} {--note=Concesión del administrador}';

    protected $description = 'Grant (or correct) a student\'s AI assistant credits for this month';

    public function handle(Credits $credits): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        $amount = (int) $this->argument('credits');
        if ($user === null || $amount === 0) {
            $this->error($user === null ? 'No user has that e-mail.' : 'The number of credits cannot be 0.');

            return self::FAILURE;
        }

        $entry = $credits->grant($user, $amount, (string) $this->option('note'));
        AuditLog::record(null, 'assistant.credits_granted', $user, ['credits' => $amount, 'entry' => $entry->id, 'via' => 'console']);
        $this->info("{$amount} credits for {$user->email} in ".Credits::period().'.');

        return self::SUCCESS;
    }
}
