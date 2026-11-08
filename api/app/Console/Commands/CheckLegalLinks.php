<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Opens every official link of config/legal.php and says which ones no
 * longer answer, or no longer show the law they should. Official sites move
 * pages (a yearly price table, a reorganised tax portal): run this now and
 * then from a machine with internet, and fix what it lists.
 */
class CheckLegalLinks extends Command
{
    protected $signature = 'legal:check-links {--timeout=20 : Seconds per page}';

    protected $description = 'Check that every official link of the course still answers';

    public function handle(): int
    {
        $links = [];
        foreach (config('legal.laws') as $key => $law) {
            $links[] = [$key, $law['url'], $this->number($law['title'])];
        }
        foreach (config('legal.regions') as $key => $region) {
            foreach ($region['links'] as $link) {
                $links[] = ["{$key} · {$link['kind']}", $link['url'], null];
            }
        }

        $failed = 0;
        foreach ($links as [$label, $url, $number]) {
            try {
                $response = Http::timeout((int) $this->option('timeout'))->withHeaders(['User-Agent' => 'Construye Academy link check'])->get($url);
                $ok = $response->successful() && ($number === null || str_contains($response->body(), $number));
                $status = $response->status().($ok ? '' : ($response->successful() ? ' (no muestra '.$number.')' : ''));
            } catch (Throwable $e) {
                $ok = false;
                $status = class_basename($e);
            }
            if (! $ok) {
                $failed++;
            }
            $this->line(sprintf('%s  %-38s %s  %s', $ok ? 'OK ' : 'MAL', $label, $status, $url));
        }

        $this->newLine();
        $this->info(sprintf('%d enlaces, %d con problemas.', count($links), $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** "Ley 38/1999, de 5 de noviembre…" → "38/1999": what the page must show. */
    private function number(string $title): ?string
    {
        return preg_match('#\d+/\d{4}#', $title, $match) === 1 ? $match[0] : null;
    }
}
