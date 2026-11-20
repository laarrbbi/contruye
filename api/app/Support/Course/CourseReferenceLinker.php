<?php

namespace App\Support\Course;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * The course sends the reader to another of its lessons or modules by name:
 * «autorizaciones sectoriales (Lección 4.2)», «método residual, Módulo 2».
 * Each such reference becomes a link to that place, in every language the
 * course is written in, without changing a word. The link carries only the
 * number (#leccion-4.2, #modulo-2): the player finds the lesson in the
 * student's outline (web: CourseReferences). Never in a heading, a law's link
 * or code.
 */
final class CourseReferenceLinker
{
    /**
     * «Lección 4.2», «Lesson F.3», «Leçon 4.2», «الدرس 4.2», «Урок 4.2» and
     * the Russian and Ukrainian cases («уроке», «уроці»); «Módulo 9»,
     * «Module 9», «الوحدة 9», «модуле 9», «модулі 9».
     */
    private const PATTERN = '/(?<![\p{L}\p{N}])(?:'
        .'(?:[Ll]ecci[oó]n|[Ll]esson|[Ll]eçon|[Уу]ро[кц]\p{L}*|الدرس)\s+(?<lesson>\d{1,2}\.\d{1,2}|F\.\d{1,2})'
        .'|(?:[Mm]ódulo|[Mm]odule|[Мм]одул\p{L}*|الوحدة)\s+(?<module>\d{1,2})'
        .')(?![\p{N}]|[.,]\d)/u';

    public function __invoke(DocumentParsedEvent $event): void
    {
        $texts = [];
        $walker = $event->getDocument()->walker();
        while ($step = $walker->next()) {
            $node = $step->getNode();
            if ($step->isEntering() && $node instanceof Text && ! $this->excluded($node)) {
                $texts[] = $node;
            }
        }

        foreach ($texts as $text) {
            $this->wrap($text);
        }
    }

    private function wrap(Text $text): void
    {
        $literal = $text->getLiteral();
        if (preg_match_all(self::PATTERN, $literal, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 0) {
            return;
        }

        $position = 0;
        foreach ($matches as $match) {
            [$reference, $offset] = $match[0];
            if ($offset > $position) {
                $text->insertBefore(new Text(substr($literal, $position, $offset - $position)));
            }

            $lesson = $match['lesson'][0] ?? null;
            [$fragment, $attribute, $value] = $lesson !== null
                ? ['leccion-'.$lesson, 'data-lesson', $lesson]
                : ['modulo-'.$match['module'][0], 'data-module', (string) $match['module'][0]];

            $link = new Link('#'.$fragment, $reference);
            $link->data->set('attributes', ['class' => 'ref-curso', $attribute => $value]);
            $text->insertBefore($link);
            $position = $offset + strlen($reference);
        }

        if ($position < strlen($literal)) {
            $text->insertBefore(new Text(substr($literal, $position)));
        }
        $text->detach();
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
}
