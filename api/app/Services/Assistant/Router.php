<?php

namespace App\Services\Assistant;

/**
 * Where a question enters the cascade of models (config assistant.cascade).
 *
 * The small model is tried first for almost everything: it costs a twentieth
 * of the medium one, and a question that turns out to be beyond it is handed
 * over (the consultar_al_experto tool) for the price of that one cheap try.
 * So the router sends a question higher only when what it asks for is plain:
 * an analysis asked for outright («analiza», «compara», «simula»), with the
 * figures to do it, a long text, several questions in one. Naming a subject
 * is not asking for a study of it: «¿qué es la TIR?» starts at the small
 * model like any other definition.
 *
 * The score and its reasons go into the trace, so the owner can see why each
 * question went where it did, and move the thresholds (assistant.router).
 */
final class Router
{
    /** Verbs that ask for an analysis to be done, in the six languages of the course. */
    private const ASKS = '/\b(analiza\w*|anal[ií]c\w+|comp[aá]r[aeoáé]\w*|simul[ae]\w*|eval[uú][ae]\w*|val[oó]r[ae]\w*|optimiza\w*|analy[sz]e\w*|compare\w*|simulate\w*|assess\w*|evaluate\w*|analyse[rz]\w*|compare[rz]\w*|simule[rz]\w*|[eé]value[rz]\w*|проанализируй\w*|сравни\w*|оцени\w*|смоделируй\w*|проаналізуй\w*|порівняй\w*|оціни\w*|змоделюй\w*|حل[ّ]?ل|قارن|قي[ّ]?م)\b/iu';

    /** What an analysis is about. */
    private const TOPICS = '/\b(viabilidad|rentabilidad|an[aá]lisis|escenarios?|sensibilidad|flujos?\s+de\s+caja|cash[\s-]?flow|TIR|VAN|IRR|NPV|ROE|plan\s+de\s+negocio|business\s+plan|estrategia|feasibility|profitability|scenarios?|faisabilit[eé]|rentabilit[eé]|рентабельн\w*|окупаем\w*|сценари\w*|аналіз\w*|анализ\w*|جدوى|ربحية|تحليل|سيناريو\w*)\b/iu';

    /** A question that asks what something is. */
    private const DEFINES = '/^[\s¿¡]*(qu[eé]\s+(es|son|significa|quiere\s+decir)|what\s+(is|are)|what\s+does|qu.est-ce\s+qu|que\s+signifie|что\s+такое|что\s+значит|що\s+таке|що\s+означає|ما\s+هو|ما\s+هي|ماذا\s+يعني)\b/iu';

    /**
     * @return array{tier: string, score: int, reasons: list<string>}
     */
    public function route(string $question): array
    {
        $reasons = [];
        $score = 0;

        if (preg_match(self::ASKS, $question) === 1) {
            $score += 2;
            $reasons[] = 'analysis';
        }
        if (preg_match(self::TOPICS, $question) === 1) {
            $score += 1;
            $reasons[] = 'topic';
        }
        $length = mb_strlen($question);
        if ($length > 900) {
            $score += 2;
            $reasons[] = 'long';
        } elseif ($length > 400) {
            $score += 1;
            $reasons[] = 'longish';
        }
        $figures = preg_match_all('/\d[\d.,]*\s*(€|eur|%|m2|m²|meses|años|viviendas)/iu', $question) >= 3;
        if ($figures) {
            $score += 1;
            $reasons[] = 'figures';
        }
        if (substr_count($question, '?') + substr_count($question, '؟') >= 3) {
            $score += 1;
            $reasons[] = 'multipart';
        }
        // «¿Qué es el análisis de viabilidad?» asks for a definition, not for the analysis.
        if ($score >= 2 && ! $figures && $length <= 160 && preg_match(self::DEFINES, $question) === 1) {
            $score = 1;
            $reasons[] = 'definition';
        }

        return ['tier' => self::entry($score), 'score' => $score, 'reasons' => $reasons];
    }

    /** The tier a score enters at, among those the cascade has. */
    private static function entry(int $score): string
    {
        $cascade = self::cascade();
        $wanted = $score >= (int) config('assistant.router.large_at', 5) ? 'large'
            : ($score >= (int) config('assistant.router.medium_at', 2) ? 'medium' : 'small');
        // A cascade without that tier (two models, or one): the nearest one below it, else the first.
        foreach (array_reverse(array_slice(['small', 'medium', 'large'], 0, array_search($wanted, ['small', 'medium', 'large'], true) + 1)) as $tier) {
            if (in_array($tier, $cascade, true)) {
                return $tier;
            }
        }

        return $cascade[0];
    }

    /** @return list<string> the tiers, cheapest first */
    public static function cascade(): array
    {
        $cascade = array_values(array_filter((array) config('assistant.cascade', ['small', 'large']), fn ($tier) => is_array(config("assistant.models.{$tier}"))));

        return $cascade === [] ? ['small'] : $cascade;
    }

    /** The tier a model hands a question to, or null at the top of the cascade. */
    public static function next(string $tier): ?string
    {
        $cascade = self::cascade();
        $at = array_search($tier, $cascade, true);

        return $at === false ? null : ($cascade[$at + 1] ?? null);
    }

    /** The tier that answers when this one cannot be reached, or null at the bottom. */
    public static function previous(string $tier): ?string
    {
        $cascade = self::cascade();
        $at = array_search($tier, $cascade, true);

        return $at === false || $at === 0 ? null : $cascade[$at - 1];
    }
}
