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
 *
 * What makes the few pieces it returns the right ones:
 * - The words are weighed against what THIS student can read: the offices of
 *   the other 51 provinces are not in the count, or «registro», «catastro»
 *   and «ayuntamiento» would weigh nothing for everyone.
 * - A student who asks in another language gets the Spanish only for what
 *   has no version in theirs (the templates' cards), never a lesson twice.
 * - No more than `retrieval.max_per_source` pieces of one lesson, text or
 *   card: the rest of the places go to other sources.
 * - A template the question names («plantilla 2») comes first among the
 *   templates' cards.
 * - «¿A qué oficina voy para…?»: the word for the place after the question
 *   word («oficina», «organismo», «which office») weighs little; what is asked
 *   about is the rest.
 * - A student with a province gets the piece of its offices (Anexo A) closest
 *   to the question, in place of the weakest piece: whenever the question asks
 *   where to go or whom to ask, and otherwise only when it is nearly as close
 *   to the question as the best piece.
 *
 * `php artisan assistant:retrieval` checks all of this on a set of questions,
 * with no model.
 */
final class Retriever
{
    private const B = 0.75;

    /** How much a piece of a template the question names is preferred to the rest. */
    private const NAMED = 1.6;

    /** The share of the best piece's score the province's offices need: when the question asks where or whom, and when it does not. */
    private const WHERE_SHARE = 0.15;

    private const NEAR_SHARE = 0.6;

    /**
     * The words a question uses for «the place», whatever the place is. After a question word
     * they say only that it asks where: «¿a qué oficina voy para la licencia?» is about the
     * licence, not about the sales office of Module 8, so there the word keeps a share of its
     * weight. Anywhere else it is the subject («¿dónde monto la oficina de ventas?», «¿qué es un
     * organismo de control técnico?») and weighs whole.
     */
    private const PLACES = 'oficina oficinas ventanilla ventanillas organismo organismos institución instituciones entidad entidades sitio lugar '
        .'office offices desk body authority place bureau bureaux guichet organisme organismes endroit '
        .'учреждение ведомство орган инстанция установа відомство інстанція مكتب جهة هيئة';

    private const WHICH_PLACE = '/(?<![\p{L}\p{N}])(?:qu[eé]|cu[aá]l(?:es)?|which|what|quel(?:le)?s?|как\p{L}+|як\p{L}+|котр\p{L}+|أي|أية)\s+(\p{L}+)/iu';

    private const PLACE_WEIGHT = 0.3;

    /** A question that asks where to go or whom to ask, in the six languages of the course. */
    private const WHERE = '/\b(d[oó]nde|ad[oó]nde|a qui[eé]n|qui[eé]n|oficinas?|organismos?|ventanilla|instituci[oó]n(?:es)?|qu[eé] (?:colegio|notar[ií]a|registro|ayuntamiento|administraci[oó]n)|where|who|whom|which (?:office|body|authority|registry|association)|offices?|o[uù]|qui|quel (?:bureau|organisme|service|ordre)|bureaux?)\b|где|куда|к кому|кто|в как\p{L}+ (?:орган|ведомств|учрежден)|де |куди|до кого|хто|в як\p{L}+ (?:орган|відомств|установ)|أين|إلى أين|من يمنح|من هو|من هي|أي (?:مكتب|جهة|هيئة)/iu';

    /**
     * @param  list<int>  $exclude  pieces already given in this answer
     * @param  list<string>  $named  titles (or parts of one) the question names: their pieces first
     * @return list<array{id: int, title: string, path: ?string, text: string, access: string, module_id: ?int, kind: string, score: float}>
     */
    public function search(string $query, string $locale, Scope $scope, int $limit, array $exclude = [], array $named = []): array
    {
        $terms = array_values(array_unique(Tokenizer::terms($query)));
        if ($terms === [] || $limit < 1) {
            return [];
        }

        $k1 = (float) config('assistant.retrieval.k1', 1.2);
        $power = (float) config('assistant.retrieval.idf_power', 1.0);
        $inTitle = (float) config('assistant.retrieval.weights.title', 1.0);
        $inLead = (float) config('assistant.retrieval.weights.lead', 0.0);

        $weak = self::placeWords($query);

        $scores = [];
        $meta = [];
        foreach (array_values(array_unique([$locale, 'es'])) as $index => $loc) {
            $visible = function ($query) use ($scope, $loc, $locale, $index) {
                // The student's own province's offices, nobody else's.
                $query->where(fn ($w) => $scope->province === null
                    ? $w->whereNull('assistant_chunks.province_id')
                    : $w->whereNull('assistant_chunks.province_id')->orWhere('assistant_chunks.province_id', $scope->province));
                // Spanish for a reader of another language: only what has no version in theirs.
                if ($index > 0) {
                    $query->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('assistant_chunks as own')
                        ->where('own.locale', $locale)
                        ->whereColumn('own.source_type', 'assistant_chunks.source_type')
                        ->whereColumn('own.source_id', 'assistant_chunks.source_id'));
                }

                return $query->where('assistant_chunks.locale', $loc);
            };

            $stats = $visible(DB::table('assistant_chunks'))->selectRaw('count(*) as n, avg(length) as avgdl')->first();
            $n = (int) ($stats->n ?? 0);
            if ($n === 0) {
                continue;
            }
            $avgdl = max(1.0, (float) $stats->avgdl);

            $postings = $visible(DB::table('assistant_postings')
                ->join('assistant_chunks', 'assistant_chunks.id', '=', 'assistant_postings.chunk_id')
                ->where('assistant_postings.locale', $loc)
                ->whereIn('assistant_postings.term', $terms))
                ->get(['assistant_postings.term', 'assistant_postings.chunk_id', 'assistant_postings.tf', 'assistant_postings.title_tf', 'assistant_postings.lead_tf', 'assistant_chunks.length',
                    'assistant_chunks.source_type', 'assistant_chunks.source_id', 'assistant_chunks.module_id', 'assistant_chunks.province_id',
                    'assistant_chunks.access', 'assistant_chunks.title']);

            $df = $postings->countBy('term');
            foreach ($postings as $posting) {
                // A rare word says more about what is asked than a common one: its weight, to a power.
                $idf = log(1 + ($n - $df[$posting->term] + 0.5) / ($df[$posting->term] + 0.5)) ** $power
                    * (isset($weak[$posting->term]) ? self::PLACE_WEIGHT : 1.0);
                // A word of the title, of a heading or of a bold opening counts as several of the lines.
                $tf = (float) $posting->tf + $inTitle * (float) $posting->title_tf + $inLead * (float) $posting->lead_tf;
                $norm = $tf * ($k1 + 1) / ($tf + $k1 * (1 - self::B + self::B * ((float) $posting->length / $avgdl)));
                $scores[$posting->chunk_id] = ($scores[$posting->chunk_id] ?? 0) + $idf * $norm;
                $meta[$posting->chunk_id] ??= $posting;
            }
        }

        foreach ($exclude as $id) {
            unset($scores[$id]);
        }
        // What the student may not read never competes (Scope is the one gate).
        foreach ($scores as $id => $score) {
            $row = $meta[$id];
            if (! $scope->allows(['access' => $row->access, 'module_id' => $row->module_id, 'province_id' => $row->province_id])) {
                unset($scores[$id]);

                continue;
            }
            if ($named !== [] && $row->source_type === 'template') {
                foreach ($named as $needle) {
                    if (str_contains((string) $row->title, $needle)) {
                        $scores[$id] = $score * self::NAMED;
                        break;
                    }
                }
            }
        }
        arsort($scores);

        // The best pieces, no more than a few from the same source.
        $perSource = max(1, (int) config('assistant.retrieval.max_per_source', 3));
        $taken = [];
        $chosen = [];
        foreach ($scores as $id => $score) {
            $source = $meta[$id]->source_type.':'.$meta[$id]->source_id.':'.$meta[$id]->module_id;
            // A template's card is several things in one (its cells, its formulas, its example): one more of it.
            if (($taken[$source] ?? 0) >= $perSource + ($meta[$id]->source_type === 'template' ? 1 : 0)) {
                continue;
            }
            $taken[$source] = ($taken[$source] ?? 0) + 1;
            $chosen[] = $id;
            if (count($chosen) >= $limit) {
                break;
            }
        }

        // The offices of the student's province: the piece of them closest to the question, in
        // place of the weakest piece. A question that asks where or whom («¿a qué oficina voy?»)
        // gets it whenever it shares a word with the question; any other, only when it is nearly
        // as close to the question as the best piece found.
        if ($scope->province !== null && $chosen !== []) {
            $top = (float) $scores[$chosen[0]];
            foreach ($scores as $id => $score) {
                if ($meta[$id]->province_id === null) {
                    continue;
                }
                if (! in_array($id, $chosen, true) && $score >= $top * (self::asksWhere($query) ? self::WHERE_SHARE : self::NEAR_SHARE)) {
                    if (count($chosen) >= $limit) {
                        array_pop($chosen);
                    }
                    $chosen[] = $id;
                }
                break;
            }
        }

        $chunks = AssistantChunk::query()->whereIn('id', $chosen)->get()->keyBy('id');
        $found = [];
        foreach ($chosen as $id) {
            $chunk = $chunks[$id] ?? null;
            if ($chunk === null) {
                continue;
            }
            $found[] = [
                'id' => (int) $chunk->id,
                'title' => (string) $chunk->title,
                'path' => $chunk->path,
                'text' => (string) $chunk->text,
                'access' => (string) $chunk->access,
                'module_id' => $chunk->module_id === null ? null : (int) $chunk->module_id,
                'kind' => (string) $chunk->source_type,
                'score' => round($scores[$id], 4),
            ];
        }

        return $found;
    }

    public static function asksWhere(string $question): bool
    {
        return preg_match(self::WHERE, $question) === 1;
    }

    /**
     * The terms of a question that only name «the place» it asks for («qué oficina», «which office»).
     *
     * @return array<string, true>
     */
    public static function placeWords(string $question): array
    {
        if (preg_match_all(self::WHICH_PLACE, $question, $found) === 0) {
            return [];
        }
        $places = array_flip(Tokenizer::terms(self::PLACES));
        $weak = [];
        foreach (Tokenizer::terms(implode(' ', $found[1])) as $term) {
            if (isset($places[$term])) {
                $weak[$term] = true;
            }
        }

        return $weak;
    }
}
