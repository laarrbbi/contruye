<?php

namespace App\Support\Glossary;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use SplObjectStorage;

/**
 * Marks the taxes and institutions the course text names (config/glossary.php)
 * so the reader can see what each one is: once per paragraph or table cell, on
 * its first mention, never in a heading, a link (a law) or code. The words stay
 * exactly as the instructor wrote them; only an «i» and a tooltip are added
 * when the Markdown is rendered.
 */
final class GlossaryLinker
{
    private static ?string $pattern = null;

    /** @var array<string, string> literal => glossary key */
    private static array $keys = [];

    public function __invoke(DocumentParsedEvent $event): void
    {
        if (! $this->pattern()) {
            return;
        }

        /** @var SplObjectStorage<Node, list<Text>> $blocks */
        $blocks = new SplObjectStorage;
        $walker = $event->getDocument()->walker();
        while ($step = $walker->next()) {
            $node = $step->getNode();
            if ($step->isEntering() && $node instanceof Text && ! $this->excluded($node)) {
                $block = $this->block($node);
                $blocks[$block] = [...($blocks->contains($block) ? $blocks[$block] : []), $node];
            }
        }

        foreach ($blocks as $block) {
            $seen = [];
            foreach ($blocks[$block] as $text) {
                $this->mark($text, $seen);
            }
        }
    }

    /** @param array<string, true> $seen keys already marked in this block */
    private function mark(Text $text, array &$seen): void
    {
        $literal = $text->getLiteral();
        if (preg_match_all((string) self::$pattern, $literal, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        $position = 0;
        $changed = false;
        foreach ($matches[0] as [$term, $offset]) {
            $key = self::$keys[$term] ?? null;
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($offset > $position) {
                $text->insertBefore(new Text(substr($literal, $position, $offset - $position)));
            }
            $text->insertBefore(new GlossaryTerm($key, $term));
            $position = $offset + strlen($term);
            $changed = true;
        }

        if (! $changed) {
            return;
        }
        if ($position < strlen($literal)) {
            $text->insertBefore(new Text(substr($literal, $position)));
        }
        $text->detach();
    }

    private function pattern(): ?string
    {
        if (self::$pattern === null) {
            self::$keys = [];
            foreach (config('glossary.terms', []) as $key => $term) {
                foreach ($term['match'] as $literal) {
                    self::$keys[$literal] = $key;
                }
            }
            $literals = array_keys(self::$keys);
            usort($literals, fn (string $a, string $b) => strlen($b) <=> strlen($a));
            self::$pattern = $literals === [] ? '' : '/(?<![\p{L}\p{N}])(?:'
                .implode('|', array_map(fn (string $literal) => preg_quote($literal, '/'), $literals))
                .')(?![\p{L}\p{N}])/u';
        }

        return self::$pattern !== '' ? self::$pattern : null;
    }

    /** Forget the compiled terms (tests that change config('glossary')). */
    public static function flush(): void
    {
        self::$pattern = null;
        self::$keys = [];
    }

    private function excluded(Node $node): bool
    {
        for ($parent = $node->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof Link || $parent instanceof Heading || $parent instanceof Code) {
                return true;
            }
        }

        return false;
    }

    /** The paragraph, cell or list item a piece of text belongs to. */
    private function block(Node $node): Node
    {
        $parent = $node->parent();
        while ($parent !== null && ! $parent instanceof AbstractBlock) {
            $parent = $parent->parent();
        }

        return $parent ?? $node;
    }
}
