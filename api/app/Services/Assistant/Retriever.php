<?php

namespace App\Services\Assistant;

use App\Models\AssistantChunk;
use Illuminate\Support\Facades\DB;

/**
 * BM25 over the course index (Indexer), in the student's language and in
 * Spanish, keeping only the pieces the student may read (Scope). A lexical
 * search, on purpose: no third-party embedding service sees the course, it
 * runs on MySQL and on the preview's SQLite alike, and the model writes its
 * own Spanish queries through the buscar_en_el_curso tool when the student
 * asks in another language.
 */
final class Retriever
{
    private const K1 = 1.2;

    private const B = 0.75;

    /**
     * @param  list<int>  $exclude  pieces already given in this answer
     * @return list<array{id: int, title: string, path: ?string, text: string, access: string, module_id: ?int, score: float}>
     */
    public function search(string $query, string $locale, Scope $scope, int $limit, array $exclude = []): array
    {
        $terms = array_values(array_unique(Tokenizer::terms($query)));
        if ($terms === []) {
            return [];
        }

        $locales = array_values(array_unique([$locale, 'es']));
        $scores = [];
        foreach ($locales as $index => $loc) {
            $stats = DB::table('assistant_chunks')->where('locale', $loc)->selectRaw('count(*) as n, avg(length) as avgdl')->first();
            $n = (int) ($stats->n ?? 0);
            if ($n === 0) {
                continue;
            }
            $avgdl = max(1.0, (float) $stats->avgdl);

            $postings = DB::table('assistant_postings')
                ->join('assistant_chunks', 'assistant_chunks.id', '=', 'assistant_postings.chunk_id')
                ->where('assistant_postings.locale', $loc)
                ->whereIn('assistant_postings.term', $terms)
                ->get(['assistant_postings.term', 'assistant_postings.chunk_id', 'assistant_postings.tf', 'assistant_chunks.length']);

            $df = $postings->countBy('term');
            foreach ($postings as $posting) {
                $idf = log(1 + ($n - $df[$posting->term] + 0.5) / ($df[$posting->term] + 0.5));
                $tf = (float) $posting->tf;
                $norm = $tf * (self::K1 + 1) / ($tf + self::K1 * (1 - self::B + self::B * ((float) $posting->length / $avgdl)));
                // The student's own language first; Spanish, the reference, right behind.
                $weight = $index === 0 ? 1.0 : 0.9;
                $scores[$posting->chunk_id] = ($scores[$posting->chunk_id] ?? 0) + $idf * $norm * $weight;
            }
        }

        foreach ($exclude as $id) {
            unset($scores[$id]);
        }
        arsort($scores);

        $found = [];
        foreach (array_chunk(array_keys($scores), 50) as $ids) {
            $chunks = AssistantChunk::query()->whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $id) {
                $chunk = $chunks[$id] ?? null;
                if ($chunk === null || ! $scope->allows(['access' => $chunk->access, 'module_id' => $chunk->module_id])) {
                    continue;
                }
                $found[] = [
                    'id' => (int) $chunk->id,
                    'title' => (string) $chunk->title,
                    'path' => $chunk->path,
                    'text' => (string) $chunk->text,
                    'access' => (string) $chunk->access,
                    'module_id' => $chunk->module_id === null ? null : (int) $chunk->module_id,
                    'score' => round($scores[$id], 4),
                ];
                if (count($found) >= $limit) {
                    return $found;
                }
            }
        }

        return $found;
    }
}
