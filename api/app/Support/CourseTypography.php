<?php

namespace App\Support;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * The course text's two pictograms, set in type instead of emoji when the
 * Markdown is rendered (the words in the database stay as the instructor wrote
 * them): the warning sign before «Errores típicos» becomes the style of its
 * list item or paragraph (class `aviso`), and the heavy check mark of the
 * tables a plain tick.
 */
final class CourseTypography
{
    private const WARNING = ["⚠\u{FE0F}", '⚠'];

    public function __invoke(DocumentParsedEvent $event): void
    {
        $walker = $event->getDocument()->walker();
        $texts = [];
        while ($step = $walker->next()) {
            if ($step->isEntering() && $step->getNode() instanceof Text) {
                $texts[] = $step->getNode();
            }
        }

        foreach ($texts as $text) {
            $literal = $text->getLiteral();
            if (str_contains($literal, '✔')) {
                // U+2713 with the text presentation selector: a tick in the font, never an emoji.
                $literal = str_replace(["✔\u{FE0F}", '✔'], "✓\u{FE0E}", $literal);
            }
            foreach (self::WARNING as $sign) {
                if (str_contains($literal, $sign)) {
                    $literal = ltrim(str_replace($sign, '', $literal));
                    $this->mark($text);
                }
            }
            $text->setLiteral($literal);
        }
    }

    private function mark(Node $node): void
    {
        for ($parent = $node->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof ListItem || ($parent instanceof Paragraph && ! $parent->parent() instanceof ListItem)) {
                $attributes = $parent->data->get('attributes', []);
                $parent->data->set('attributes', ['class' => trim(($attributes['class'] ?? '').' aviso')] + $attributes);

                return;
            }
        }
    }
}
