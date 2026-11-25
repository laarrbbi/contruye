<?php

namespace App\Services\Assistant;

/**
 * A text of the course cut into pieces of about `retrieval.chunk_chars`, so
 * that each piece can be read on its own: the search gives the model a few
 * pieces, not the page.
 *
 * - Paragraphs are packed whole; one longer than a piece is cut at its lines.
 * - A list is cut between its items, never inside one that fits: «ICIO:» and
 *   its three lines stay together. An item longer than a piece is cut at its
 *   lines, and each further piece starts with the item's opening words.
 * - A table is cut between its rows, and each piece starts with its header:
 *   a row without its columns' names says nothing.
 * - A piece that begins in the middle of a section starts with the section's
 *   heading («### Ejemplo: Nicolás, el promotor (…)»), and one that begins
 *   in the middle of a list, with what the list is («**Entradas** (…)»): the
 *   words a question is asked with are often there, not in the lines below.
 *
 * What is repeated is marked «(…)»: nothing is added that the text does not
 * say, and no word of it is changed.
 */
final class Chunker
{
    private const MARK = ' (…)';

    /** @return list<string> */
    public static function split(string $text, int $limit): array
    {
        $pieces = [];
        $current = '';
        $heading = null;
        $opens = false;

        foreach (preg_split('/\n\s*\n/', trim(str_replace("\r", '', $text))) ?: [] as $block) {
            $block = trim($block, "\n");
            if ($block === '') {
                continue;
            }
            // A heading says what the lines below it are: it travels with them, never alone.
            if (preg_match('/^(#{1,4}\s+\S[^\n]*)(?:\n(.*))?$/s', $block, $m) === 1) {
                $heading = rtrim($m[1]);
                $opens = true;
                $block = trim((string) ($m[2] ?? ''), "\n");
                if ($block === '') {
                    continue;
                }
            }

            foreach (self::units($block, $limit) as $index => [$unit, $lead]) {
                $inline = ($opens && $heading !== null ? $heading."\n\n" : '').$unit;
                // A new section starts a new piece, unless what came before is only a few lines.
                $boundary = $opens && mb_strlen($current) >= $limit / 3;
                if ($current !== '' && ! $boundary && mb_strlen($current) + mb_strlen($inline) + 2 <= $limit) {
                    $current .= ($index === 0 ? "\n\n" : "\n").$inline;
                } else {
                    if ($current !== '') {
                        $pieces[] = $current;
                    }
                    $current = ($heading === null ? '' : $heading.($opens ? '' : self::MARK)."\n\n").($lead === null ? '' : $lead."\n").$unit;
                }
                $opens = false;
            }
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return array_values(array_filter(array_map('trim', $pieces), fn (string $piece) => $piece !== ''));
    }

    /**
     * A block as the parts it may be cut into, each with what a piece that
     * starts at it must begin with (null for the block's first part).
     *
     * @return list<array{0: string, 1: ?string}>
     */
    private static function units(string $block, int $limit): array
    {
        if (mb_strlen($block) <= $limit) {
            return [[$block, null]];
        }
        $lines = explode("\n", $block);
        if (count($lines) === 1) {
            return [[$block, null]];
        }

        // A table: its header with every piece.
        if (str_starts_with(ltrim($lines[0]), '|') && preg_match('/^\s*\|[\s:|-]+$/', $lines[1]) === 1) {
            $header = $lines[0]."\n".$lines[1];
            $units = [];
            foreach (array_slice($lines, 2) as $index => $row) {
                $units[] = $index === 0 ? [$header."\n".$row, null] : [$row, $header];
            }

            return $units === [] ? [[$block, null]] : $units;
        }

        $item = '/^(?:[-*+]|\d+[.)])\s+/';
        $isList = preg_match($item, $lines[0]) === 1;
        $hasItems = $isList || count(array_filter(array_slice($lines, 1), fn (string $line) => preg_match($item, $line) === 1)) > 0;
        if (! $hasItems) {
            // A paragraph of several lines: at its lines, each further piece under its opening words.
            $lead = self::short($lines[0]);

            return array_map(fn (string $line, int $index) => [$line, $index === 0 ? null : $lead], $lines, array_keys($lines));
        }

        // A list. A line that is not an item says what the items below it are: it stays
        // with the first of them, and the others say it again when they open a piece.
        $about = $isList ? null : self::short($lines[0]);
        $groups = [];
        foreach ($lines as $n => $line) {
            $starts = preg_match($item, $line) === 1;
            $says = ! $starts && preg_match('/^\S/', $line) === 1 && ! $isList;
            if ($n === 0 || $says) {
                $groups[] = ['lead' => $starts ? null : $line, 'items' => $starts ? [$line] : []];
            } elseif ($starts) {
                $groups[count($groups) - 1]['items'][] = $line;
            } else {
                $last = &$groups[count($groups) - 1];
                if ($last['items'] === []) {
                    $last['lead'] .= "\n".$line;
                } else {
                    $last['items'][count($last['items']) - 1] .= "\n".$line;
                }
                unset($last);
            }
        }

        $units = [];
        foreach ($groups as $g => $group) {
            $own = $group['lead'] === null ? null : self::short(explode("\n", $group['lead'])[0]);
            // What a piece opening inside this group starts with: what the block is, then what the group is.
            $context = trim(implode("\n", array_unique(array_filter([$g === 0 ? null : $about, $own]))));
            $context = $context === '' ? null : $context;
            $opening = $g === 0 ? null : $about;
            if ($group['items'] === []) {
                $units[] = [(string) $group['lead'], $opening];

                continue;
            }
            foreach ($group['items'] as $index => $text) {
                $first = $index === 0 && $group['lead'] !== null ? $group['lead']."\n" : '';
                $lead = $index === 0 ? ($first === '' ? $context : $opening) : $context;
                if (mb_strlen($first.$text) <= $limit) {
                    $units[] = [$first.$text, $lead];

                    continue;
                }
                // An item longer than a piece: at its lines, under its own opening words.
                $inner = explode("\n", $text);
                $head = self::short($inner[0]);
                foreach ($inner as $n => $line) {
                    $units[] = [$n === 0 ? $first.$line : $line, $n === 0 ? $lead : trim(($context === null ? '' : $context."\n").$head)];
                }
            }
        }
        if ($units !== []) {
            $units[0][1] = null;
        }

        return $units;
    }

    /** The opening words of a line, to say again where its text goes on: its bold start, or its first words. */
    private static function short(string $line): string
    {
        if (mb_strlen($line) <= 160) {
            return rtrim($line).self::MARK;
        }
        if (preg_match('/^(\s*(?:(?:[-*+]|\d+[.)])\s+|>\s*)?(?:⚠️\s*)?\*\*[^*\n]{1,160}\*\*:?)/u', $line, $m) === 1) {
            return rtrim($m[1]).self::MARK;
        }

        // A long line that announces what follows («Lo que le sale: …»): up to its colon.
        $start = mb_substr($line, 0, 140);
        $colon = mb_strpos($start, ':');

        return ($colon !== false && $colon >= 12 ? mb_substr($start, 0, $colon + 1) : rtrim($start).'…').self::MARK;
    }
}
