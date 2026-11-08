<?php

namespace App\Support\Legal;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use SplObjectStorage;

/**
 * Links every law the text names to its official page, once per paragraph
 * or table cell (on its numbered form when the acronym is there too), after
 * the Markdown is parsed: the words stay exactly as written, only wrapped in
 * a link that opens the BOE (or the regional bulletin) in a new tab. Text
 * already inside a link or a code span is left alone.
 */
final class LegalLinker
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $sources = LegalSources::instance();

        // The text of each paragraph, cell or heading, grouped by that block.
        /** @var SplObjectStorage<Node, list<Text>> $blocks */
        $blocks = new SplObjectStorage;
        $walker = $event->getDocument()->walker();
        while ($step = $walker->next()) {
            $node = $step->getNode();
            if ($step->isEntering() && $node instanceof Text && ! $this->insideLink($node)) {
                $block = $this->block($node);
                $blocks[$block] = [...($blocks->contains($block) ? $blocks[$block] : []), $node];
            }
        }

        foreach ($blocks as $block) {
            $context = $this->plain($block);

            // Every reference in the block, then one link per law: its numbered
            // form ("RDL 7/2015") over its acronym ("TRLSRU"), else the first.
            $found = [];
            foreach ($blocks[$block] as $index => $text) {
                foreach ($sources->find($text->getLiteral(), $context) as $match) {
                    $found[] = ['node' => $index] + $match;
                }
            }
            $chosen = [];
            foreach ($found as $match) {
                $current = $chosen[$match['key']] ?? null;
                if ($current === null || (! $this->numbered($current['text']) && $this->numbered($match['text']))) {
                    $chosen[$match['key']] = $match;
                }
            }

            $byText = [];
            foreach ($chosen as $match) {
                $byText[$match['node']][] = $match;
            }
            foreach ($byText as $index => $matches) {
                usort($matches, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);
                $this->wrap($blocks[$block][$index], $matches);
            }
        }
    }

    /** @param list<array{offset: int, text: string, law: array{title: string, url: string}}> $matches */
    private function wrap(Text $text, array $matches): void
    {
        $literal = $text->getLiteral();
        $position = 0;
        foreach ($matches as $match) {
            if ($match['offset'] > $position) {
                $text->insertBefore(new Text(substr($literal, $position, $match['offset'] - $position)));
            }
            $link = new Link($match['law']['url'], $match['text'], $match['law']['title']);
            $link->data->set('attributes', ['class' => 'ley', 'target' => '_blank', 'rel' => 'noopener noreferrer']);
            $text->insertBefore($link);
            $position = $match['offset'] + strlen($match['text']);
        }
        if ($position < strlen($literal)) {
            $text->insertBefore(new Text(substr($literal, $position)));
        }
        $text->detach();
    }

    /** "RDL 7/2015", "Orden ECO/805/2003": a reference by its number, not its acronym. */
    private function numbered(string $reference): bool
    {
        return preg_match('#\d+/\d{2,4}#', $reference) === 1;
    }

    private function insideLink(Node $node): bool
    {
        for ($parent = $node->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof Link) {
                return true;
            }
        }

        return false;
    }

    /** The paragraph, cell or heading a piece of text belongs to. */
    private function block(Node $node): Node
    {
        $parent = $node->parent();
        while ($parent !== null && ! $parent instanceof AbstractBlock) {
            $parent = $parent->parent();
        }

        return $parent ?? $node;
    }

    private function plain(Node $block): string
    {
        $plain = '';
        $walker = $block->walker();
        while ($step = $walker->next()) {
            $node = $step->getNode();
            if ($step->isEntering() && ($node instanceof Text || $node instanceof Code)) {
                $plain .= $node->getLiteral().' ';
            }
        }

        return $plain;
    }
}
