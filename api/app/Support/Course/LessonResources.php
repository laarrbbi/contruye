<?php

namespace App\Support\Course;

use App\Models\Company;
use App\Models\Lesson;
use App\Models\Partner;

/**
 * What a lesson sends the student to, shown under its text
 * (config/lesson_resources.php): the websites it names (a portal, an
 * auction site, the Catastro…), each at its official address, and the kinds
 * of partner it calls on (real-estate agencies where it talks about finding
 * land, a bank for the developer loan…), from the company directory and the
 * academy's own partners. The lesson's text is never touched: a link is
 * listed only for a name the text says (LessonResourcesTest), and laws are
 * linked inside the text already (Support/Legal).
 */
final class LessonResources
{
    /** 'Lección 1.1 — …' → '1.1', 'Lección F.3 — …' → 'F.3'. */
    public static function number(Lesson $lesson): ?string
    {
        return preg_match('/^Lección\s+([0-9]+|F)\.([0-9]+)\b/u', (string) $lesson->title_es, $m) === 1 ? "{$m[1]}.{$m[2]}" : null;
    }

    /**
     * The lesson's entry, as configured: links, directory categories and
     * academy partner categories.
     *
     * @return array{links: list<array{name: string, url: string, mention: string}>, companies: list<string>, partners: list<string>}
     */
    public static function entry(Lesson $lesson): array
    {
        $number = self::number($lesson);
        $entry = $number === null ? null : (config('lesson_resources.lessons') ?? [])[$number] ?? null;

        return [
            'links' => array_values($entry['links'] ?? []),
            'companies' => array_values(array_intersect($entry['companies'] ?? [], array_keys(config('directory.categories')))),
            'partners' => array_values(array_intersect($entry['partners'] ?? [], Partner::CATEGORIES)),
        ];
    }

    /**
     * What the student's page gets with the lesson's text, behind the same
     * gate: the links, and the kinds of partner with how many companies of
     * the directory there are for them.
     *
     * @return array{links: list<array{name: string, url: string}>, companies: list<string>, companies_count: int, partners: list<string>}
     */
    public static function payload(Lesson $lesson): array
    {
        $entry = self::entry($lesson);

        return [
            'links' => array_map(fn (array $link) => ['name' => $link['name'], 'url' => $link['url']], $entry['links']),
            'companies' => $entry['companies'],
            'companies_count' => $entry['companies'] === [] ? 0 : Company::query()->visible()->whereIn('category', $entry['companies'])->count(),
            'partners' => $entry['partners'],
        ];
    }
}
