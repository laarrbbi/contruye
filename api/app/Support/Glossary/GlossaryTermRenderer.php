<?php

namespace App\Support\Glossary;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * The term as written, a small «i» after it, and the explanation the
 * pointer (or the keyboard's focus) brings up: the name in full and one or
 * two sentences, in the reader's language (lang/<locale>/glossary.php).
 * Plain HTML and CSS: nothing to load, nothing to click.
 */
final class GlossaryTermRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        assert($node instanceof GlossaryTerm);

        $entry = "glossary.{$node->key}";
        $name = Xml::escape(__($entry.'.name'));
        $text = Xml::escape(__($entry.'.text'));

        return new HtmlElement('span', ['class' => 'term', 'tabindex' => '0'], [
            Xml::escape($node->literal),
            new HtmlElement('span', ['class' => 'term-i', 'aria-hidden' => 'true'], 'i'),
            new HtmlElement('span', ['class' => 'term-tip', 'role' => 'tooltip'], [
                new HtmlElement('strong', [], $name),
                new HtmlElement('span', [], $text),
            ]),
        ]);
    }
}
