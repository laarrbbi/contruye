<?php

namespace App\Console\Commands;

use App\Support\Legal\WebLinks;
use Illuminate\Console\Command;

/**
 * Writes the laws the app's own copy names, with their official links, for
 * the web app (WebLinks). Run it after changing config/legal.php or an FAQ
 * answer that names a law; LegalLinksTest says when it is due.
 */
class ExportWebLegalLinks extends Command
{
    protected $signature = 'legal:export-web';

    protected $description = 'Write the official links of the laws the web app names (web/src/content/legal-links.json)';

    public function handle(): int
    {
        $payload = WebLinks::payload();
        file_put_contents(WebLinks::path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info(count($payload['links']).' enlaces → '.WebLinks::path());

        return self::SUCCESS;
    }
}
