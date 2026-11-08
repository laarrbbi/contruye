<?php

namespace App\Support\Legal;

/**
 * The laws the course cites (config/legal.php) and how to find them in a
 * text: every reference as the course writes it ("RDL 7/2015", "LOE",
 * "Código Civil"), as a whole word, longest first. Where two laws share a
 * number ("Ley 13/2015": the State's reform of the Ley Hipotecaria, and
 * Murcia's planning law), the one whose `when` matches the surrounding
 * paragraph or cell wins, and the one without a `when` is the default.
 */
final class LegalSources
{
    private static ?self $instance = null;

    /** @var array<string, list<string>> literal => law keys, those with a `when` first */
    private array $byLiteral = [];

    private string $pattern;

    /** @param array<string, array{title: string, url: string, match: list<string>, when?: string}> $laws */
    public function __construct(private readonly array $laws)
    {
        foreach ($laws as $key => $law) {
            foreach ($law['match'] as $literal) {
                $this->byLiteral[$literal][] = $key;
            }
        }
        foreach ($this->byLiteral as $literal => $keys) {
            usort($keys, fn (string $a, string $b) => isset($laws[$b]['when']) <=> isset($laws[$a]['when']));
            $this->byLiteral[$literal] = $keys;
        }

        $literals = array_keys($this->byLiteral);
        usort($literals, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        // A whole reference: no letter, digit or slash glued to either side (nor
        // "-bis" after it), and "Decreto Legislativo 1/2004" never inside
        // "Real Decreto Legislativo 1/2004".
        $this->pattern = '/(?<![\p{L}\p{N}\/])(?<!Real )(?:'
            .implode('|', array_map(fn (string $literal) => preg_quote($literal, '/'), $literals))
            .')(?![\p{L}\p{N}\/]|-[\p{L}\p{N}])/u';
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(config('legal.laws', []));
    }

    /** Drop the cached instance (tests that change config('legal')). */
    public static function flush(): void
    {
        self::$instance = null;
    }

    /**
     * The references in $text, in order, each with its law.
     *
     * @param  string  $context  the paragraph or cell $text belongs to, for `when`
     * @return list<array{offset: int, text: string, key: string, law: array{title: string, url: string}}>
     */
    public function find(string $text, string $context): array
    {
        if ($this->byLiteral === [] || preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $found = [];
        foreach ($matches[0] as [$literal, $offset]) {
            $key = $this->resolve($literal, $context);
            if ($key !== null) {
                $found[] = ['offset' => $offset, 'text' => $literal, 'key' => $key, 'law' => $this->laws[$key]];
            }
        }

        return $found;
    }

    /** @return array{title: string, url: string}|null */
    public function law(string $key): ?array
    {
        return $this->laws[$key] ?? null;
    }

    private function resolve(string $literal, string $context): ?string
    {
        foreach ($this->byLiteral[$literal] ?? [] as $key) {
            $when = $this->laws[$key]['when'] ?? null;
            if ($when === null || preg_match('/'.$when.'/u', $context) === 1) {
                return $key;
            }
        }

        return null;
    }
}
