<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Protection\ContentFingerprint;
use Illuminate\Console\Command;

/**
 * Who a copy of the course came from: paste the text found elsewhere into a
 * file (or pipe it in) and the accounts whose invisible mark it carries are
 * listed, the most frequent first.
 */
class TraceContent extends Command
{
    protected $signature = 'content:trace {file? : A text file with the copied text; without it, the text is read from standard input}';

    protected $description = 'Name the account a copied piece of course text was served to';

    public function handle(): int
    {
        $file = $this->argument('file');
        $text = $file !== null ? @file_get_contents($file) : stream_get_contents(STDIN);

        if (! is_string($text) || $text === '') {
            $this->error('No text to read.');

            return self::FAILURE;
        }

        $found = ContentFingerprint::trace($text);

        if ($found === []) {
            $this->warn('No mark found. The text may have been retyped, or it was not copied from the course.');

            return self::FAILURE;
        }

        $users = User::query()->whereIn('id', array_keys($found))->get()->keyBy('id');

        $this->table(['User id', 'Name', 'E-mail', 'Marks found'], collect($found)->map(fn (int $count, int $id) => [
            $id,
            $users[$id]->name ?? '(deleted)',
            $users[$id]->email ?? '—',
            $count,
        ])->values()->all());

        return self::SUCCESS;
    }
}
