<?php

namespace App\Services\Assistant;

/**
 * Which model answers. Most questions are easy (what a document is, which
 * tax applies, what to do next) and go to the small model, which can still
 * hand a question over to the large one with the consultar_al_experto tool.
 * A question that asks for an analysis (a viability study, a comparison,
 * scenarios, a cash flow, a return), carries many figures, or is long and
 * in several parts goes to the large model at once. The reasons go into the
 * trace, so the admin can see why each question went where it did.
 */
final class Router
{
    /** Words that ask for an analysis, in the six languages of the course. */
    private const ANALYSIS = '/\b(viabilidad|rentabilidad|analiza\w*|an[aá]lisis|compar\w+|escenarios?|simula\w*|sensibilidad|flujos?\s+de\s+caja|cash[\s-]?flow|TIR|VAN|IRR|NPV|ROE|plan\s+de\s+negocio|business\s+plan|estrategia|feasibility|profitability|analy[sz]\w*|scenarios?|faisabilit[eé]|rentabilit[eé]|analyse\w*|сравн\w*|анализ\w*|рентабельн\w*|окупаем\w*|порівня\w*|аналіз\w*|рентабельн\w*|جدوى|ربحية|تحليل|قارن)\b/iu';

    /**
     * @return array{tier: string, reasons: list<string>}
     */
    public function route(string $question): array
    {
        $reasons = [];
        $score = 0;

        if (preg_match(self::ANALYSIS, $question)) {
            $score += 2;
            $reasons[] = 'analysis';
        }
        $length = mb_strlen($question);
        if ($length > 900) {
            $score += 2;
            $reasons[] = 'long';
        } elseif ($length > 400) {
            $score += 1;
            $reasons[] = 'longish';
        }
        if (preg_match_all('/\d[\d.,]*\s*(€|eur|%|m2|m²|meses|años|viviendas)/iu', $question) >= 3) {
            $score += 1;
            $reasons[] = 'figures';
        }
        if (substr_count($question, '?') + substr_count($question, '؟') >= 3) {
            $score += 1;
            $reasons[] = 'multipart';
        }

        return ['tier' => $score >= 2 ? 'large' : 'small', 'reasons' => $reasons];
    }
}
