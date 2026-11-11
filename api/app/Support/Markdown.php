<?php

namespace App\Support;

use App\Support\Glossary\GlossaryLinker;
use App\Support\Glossary\GlossaryTerm;
use App\Support\Glossary\GlossaryTermRenderer;
use App\Support\Legal\LegalLinker;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The course texts are Markdown as the instructor wrote them: tables,
 * bullets, bold, task lists. They are rendered here, on the server, with
 * GitHub's flavour of CommonMark, so every surface (player, landing page,
 * search-engine pages) shows the same thing.
 *
 * Raw HTML in the source is stripped and unsafe links (javascript:, data:…)
 * are dropped: the output is safe to put in the page as is, even if an admin
 * pastes something hostile into a lesson.
 *
 * Every law the text names becomes a link to its official text
 * (Legal\LegalLinker, config/legal.php), without changing a word. In the
 * course's own text ($glossary), each tax and institution also gets an «i»
 * with what it is (Glossary\GlossaryLinker, config/glossary.php); never in an
 * e-mail or a public page, which have no tooltip.
 */
final class Markdown
{
    /** @var array<string, MarkdownConverter> */
    private static array $converters = [];

    /**
     * @param  bool  $lineBreaks  keep each source line on its own line — for
     *                            short texts written line by line, like a
     *                            module's "Objetivo / Duración" header
     * @param  bool  $glossary  mark the taxes and institutions (course text in the app)
     */
    public static function toHtml(?string $markdown, bool $lineBreaks = false, bool $glossary = false): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return trim((string) self::converter($lineBreaks, $glossary)->convert($markdown));
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

    /**
     * A short plain-text field (a province's planning law) as inline HTML:
     * escaped, with its laws linked, without the wrapping paragraph.
     */
    public static function inline(?string $text, bool $glossary = false): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        // Escape Markdown's own punctuation: the field is text, not Markdown.
        $html = self::toHtml((string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $text), glossary: $glossary);

        return (string) preg_replace('#^<p>(.*)</p>$#s', '$1', (string) $html);
    }

    /** Forget the converters (tests that change config('legal')). */
    public static function flush(): void
    {
        self::$converters = [];
        GlossaryLinker::flush();
    }

    private static function converter(bool $lineBreaks, bool $glossary = false): MarkdownConverter
    {
        $key = ($lineBreaks ? 'breaks' : 'default').($glossary ? '+glossary' : '');

        if (! isset(self::$converters[$key])) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
                'renderer' => ['soft_break' => $lineBreaks ? "<br />\n" : "\n"],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
            $environment->addEventListener(DocumentParsedEvent::class, new LegalLinker);
            if ($glossary) {
                // After the laws: a term inside a law's link stays part of the link.
                $environment->addEventListener(DocumentParsedEvent::class, new GlossaryLinker, -10);
                $environment->addRenderer(GlossaryTerm::class, new GlossaryTermRenderer);
            }
            self::$converters[$key] = new MarkdownConverter($environment);
        }

        return self::$converters[$key];
    }
}
