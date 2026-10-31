<?php

namespace App\Support;

use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * The course texts are Markdown as the instructor wrote them: tables,
 * bullets, bold, task lists. They are rendered here, on the server, with
 * GitHub's flavour of CommonMark, so every surface (player, landing page,
 * search-engine pages) shows the same thing.
 *
 * Raw HTML in the source is stripped and unsafe links (javascript:, data:…)
 * are dropped: the output is safe to put in the page as is, even if an admin
 * pastes something hostile into a lesson.
 */
final class Markdown
{
    /** @var array<string, GithubFlavoredMarkdownConverter> */
    private static array $converters = [];

    /**
     * @param  bool  $lineBreaks  keep each source line on its own line — for
     *                            short texts written line by line, like a
     *                            module's "Objetivo / Duración" header
     */
    public static function toHtml(?string $markdown, bool $lineBreaks = false): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return trim((string) self::converter($lineBreaks)->convert($markdown));
    }

    /**
     * The same text without any markup, for a meta description or a search
     * result: "**Objetivo:** comprar…" → "Objetivo: comprar…".
     */
    public static function toText(?string $markdown): ?string
    {
        $html = self::toHtml($markdown);

        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br />', '</li>'], ["\n", "\n", "\n"], $html)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace("/\n{2,}/", "\n", $text));
    }

    private static function converter(bool $lineBreaks): GithubFlavoredMarkdownConverter
    {
        $key = $lineBreaks ? 'breaks' : 'default';

        return self::$converters[$key] ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'renderer' => ['soft_break' => $lineBreaks ? "<br />\n" : "\n"],
        ]);
    }
}
