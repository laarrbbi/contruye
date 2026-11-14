<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\DB;

/**
 * Frequent questions. A conversation's first question, asked again word for
 * word once normalised (case, accents, spaces, final punctuation), in the
 * same language, by a student who can read exactly the same part of the
 * course, gets the answer already given: no model call, no credit.
 *
 * The key carries everything an answer depends on: the question, the
 * language, the reader's scope (so an answer built from a module never
 * reaches someone who has not bought it), the prompt, the models, the course
 * index and the legal parameters in force. A change to any of them is a new
 * key: nothing stale is served. Never for a follow-up (it depends on the
 * conversation) or for an answer that ran a template (it holds the student's
 * own figures).
 */
final class AnswerCache
{
    public function __construct(private readonly Availability $availability) {}

    public function key(string $question, string $locale, Scope $scope): string
    {
        return hash('sha256', json_encode([
            self::normalise($question),
            $locale,
            $scope->signature(),
            $this->availability->versionKey(),
            $this->availability->legalVersion(),
        ]));
    }

    /** @return array{answer: string, sources: list<array<string, mixed>>, model: ?string}|null */
    public function get(string $key): ?array
    {
        if (! config('assistant.answer_cache.enabled')) {
            return null;
        }
        $row = DB::table('assistant_answer_cache')->where('key', $key)->where('expires_at', '>', now())->first();
        if ($row === null) {
            return null;
        }
        DB::table('assistant_answer_cache')->where('id', $row->id)->increment('hits');

        return ['answer' => (string) $row->answer, 'sources' => json_decode((string) $row->sources, true) ?: [], 'model' => $row->model];
    }

    /** @param list<array<string, mixed>> $sources */
    public function put(string $key, string $locale, string $question, string $answer, array $sources, ?string $model): void
    {
        if (! config('assistant.answer_cache.enabled')) {
            return;
        }
        $now = now();
        DB::table('assistant_answer_cache')->upsert([[
            'key' => $key,
            'locale' => $locale,
            'question' => mb_substr($question, 0, 2000),
            'answer' => $answer,
            'sources' => json_encode($sources),
            'model' => $model,
            'hits' => 0,
            'expires_at' => $now->copy()->addDays((int) config('assistant.answer_cache.ttl_days')),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['key'], ['answer', 'sources', 'model', 'expires_at', 'updated_at']);
    }

    public static function normalise(string $question): string
    {
        $text = mb_strtolower(Tokenizer::plain($question));
        $text = preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? $text;

        return trim(rtrim(trim($text), '?¿!¡.…؟ '));
    }
}
