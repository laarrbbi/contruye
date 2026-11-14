<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantTrace;
use App\Models\Course;
use App\Models\User;
use App\Support\Markdown;
use App\Support\Protection\ContentFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nicolás, start to end.
 *
 *   availability → one question at a time → input guard → frequent-question
 *   cache → credits → router → retrieval (only what the student may read) →
 *   the model, with its tools (search, templates, legal parameters, and for
 *   the small model a hand-over to the large one) → output guard →
 *   citations → credits spent → trace → cache.
 *
 * Every question leaves a trace, whatever happens to it: blocked by the
 * guard, answered from the cache, refused by the model, failed. The trace
 * holds what the admin needs (route, model, tokens, cost, credits, pieces of
 * the course retrieved, tools run, flags, time), never the key.
 *
 * respond() is the answer alone, for a scope and a question, without a
 * student, a conversation or credits: the evaluation set runs through it, so
 * what is evaluated is exactly what students get.
 */
final class Assistant
{
    private const LANGUAGES = ['es' => 'español', 'en' => 'inglés', 'fr' => 'francés', 'ar' => 'árabe', 'ru' => 'ruso', 'uk' => 'ucraniano'];

    /** The routes whose answers are the model's (or the cache's): the ones the conversation keeps as history. */
    private const ANSWERED = ['small', 'large', 'escalated', 'cache'];

    public function __construct(
        private readonly Llm $llm,
        private readonly Tools $tools,
        private readonly Guard $guard,
        private readonly Router $router,
        private readonly Credits $credits,
        private readonly AnswerCache $cache,
        private readonly Availability $availability,
    ) {}

    /**
     * A student's question.
     *
     * @return array{conversation: AssistantConversation, question: AssistantMessage, answer: AssistantMessage, balance: int, spent: int}
     *
     * @throws AssistantUnavailable
     */
    public function ask(User $user, ?AssistantConversation $conversation, string $question, string $locale): array
    {
        $started = hrtime(true);
        $course = Indexer::course();
        if ($course === null) {
            throw new AssistantUnavailable('not_configured');
        }
        $scope = Scope::for($user, $course);
        $state = $this->availability->for($user, $scope);
        if (! $state['available']) {
            $this->trace(['user_id' => $user->id, 'conversation_id' => $conversation?->id, 'route' => 'unavailable', 'status' => 'blocked', 'flags' => [$state['reason']]], $started);

            throw new AssistantUnavailable((string) $state['reason']);
        }

        // One question at a time per student: no parallel requests to empty the credits or the budget.
        $lock = Cache::lock("assistant:ask:{$user->id}", (int) ceil((float) config('assistant.anthropic.timeout') * 4));
        if (! $lock->get()) {
            throw new AssistantUnavailable('busy');
        }

        try {
            return $this->askLocked($user, $course, $scope, $conversation, $question, $locale, $started);
        } finally {
            $lock->release();
        }
    }

    /**
     * The answer alone: guard, router, retrieval, model and tools, guard, citations.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  ?int  $balance  the student's credits (null: no limit, for the evaluation)
     * @return array<string, mixed>
     */
    public function respond(Scope $scope, Course $course, string $question, string $locale, array $history = [], ?int $balance = null): array
    {
        $result = [
            'route' => 'small', 'status' => 'ok', 'text' => '', 'sources' => [], 'model' => null, 'credits' => 0,
            'usage' => ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0], 'cost' => 0.0,
            'chunks' => [], 'tools' => [], 'flags' => [], 'error' => null, 'used_template' => false,
        ];

        $checked = $this->guard->question($question);
        $result['flags'] = $checked['flags'];
        if ($checked['block'] !== null) {
            return ['route' => 'blocked', 'status' => 'blocked', 'text' => self::refusal($checked['block'], $locale)] + $result;
        }

        $routing = $this->router->route($checked['text']);
        $tier = $routing['tier'];
        $result['flags'] = [...$result['flags'], ...array_map(fn (string $r) => 'route:'.$r, $routing['reasons'])];
        if ($balance !== null && $tier === 'large' && $balance < Credits::cost('large')) {
            $tier = 'small';
            $result['flags'][] = 'downgraded';
        }
        $canEscalate = $tier === 'small' && ($balance === null || $balance >= Credits::cost('large'));

        // The pieces of the course that go with the question, before the model sees it.
        $docs = $this->tools->search($checked['text'], $locale, $scope, []);
        $result['chunks'] = array_map(fn (array $d) => ['id' => $d['id'], 'score' => $d['score']], $docs);

        $messages = [...$this->historyMessages($history), ['role' => 'user', 'content' => $this->turn($checked['text'], $locale, $scope, $course, $docs)]];
        $context = array_map(fn (array $d) => $d['text'], $docs);

        $run = $this->run($tier, $canEscalate, $messages, $docs, $context, $scope, $course, $locale, $result);
        if ($run['escalate'] ?? false) {
            $result = $run['result'];
            $result['flags'][] = 'escalated';
            $run = $this->run('large', false, $messages, $docs, $context, $scope, $course, $locale, $result);
            if ($run['result']['route'] === 'large') {
                $run['result']['route'] = 'escalated';
            }
        }
        $result = $run['result'];
        if ($result['status'] !== 'ok') {
            return $result;
        }

        $guarded = $this->guard->answer($run['text'], $run['context'], Guard::passages(Prompt::template()));
        $result['flags'] = [...$result['flags'], ...$guarded['flags']];
        if ($guarded['block'] !== null) {
            return ['status' => 'blocked', 'text' => self::refusal('answer', $locale), 'credits' => Credits::cost($result['route'] === 'escalated' ? 'large' : $result['route'])] + $result;
        }
        if (trim($guarded['text']) === '') {
            return ['status' => 'error', 'error' => 'empty', 'text' => self::refusal('error', $locale)] + $result;
        }

        [$text, $sources] = $this->cite($guarded['text'], $run['docs']);

        return ['text' => $text, 'sources' => $sources, 'credits' => Credits::cost($result['route'] === 'escalated' ? 'large' : $result['route'])] + $result;
    }

    /** @return array{conversation: AssistantConversation, question: AssistantMessage, answer: AssistantMessage, balance: int, spent: int} */
    private function askLocked(User $user, Course $course, Scope $scope, ?AssistantConversation $conversation, string $question, string $locale, int $started): array
    {
        $question = trim($question);
        $history = $conversation === null ? [] : $this->history($conversation);
        $first = $history === [];

        $checked = $this->guard->question($question);
        $cacheKey = $first && $checked['block'] === null ? $this->cache->key($checked['text'], $locale, $scope) : null;
        $hit = $cacheKey === null ? null : $this->cache->get($cacheKey);

        $balance = $this->credits->balance($user, $scope);
        if ($hit === null && $checked['block'] === null && $balance < Credits::cost('small')) {
            $this->trace(['user_id' => $user->id, 'conversation_id' => $conversation?->id, 'route' => 'refused', 'status' => 'blocked', 'flags' => ['no_credits']], $started);

            throw new AssistantUnavailable('no_credits');
        }

        $conversation ??= AssistantConversation::query()->create([
            'user_id' => $user->id,
            'title' => Str::limit(preg_replace('/\s+/u', ' ', $question) ?? $question, 80, '…'),
        ]);
        $asked = $conversation->messages()->create(['role' => 'user', 'content' => $question]);

        if ($hit !== null) {
            $result = ['route' => 'cache', 'status' => 'ok', 'text' => $hit['answer'], 'sources' => $hit['sources'], 'model' => $hit['model'], 'credits' => 0,
                'usage' => ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0], 'cost' => 0.0,
                'chunks' => [], 'tools' => [], 'flags' => $checked['flags'], 'error' => null, 'used_template' => false];
        } else {
            $result = $this->respond($scope, $course, $question, $locale, $history, $balance);
        }

        return DB::transaction(function () use ($user, $scope, $conversation, $asked, $result, $started, $first, $cacheKey, $locale, $checked) {
            $answer = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $result['text'],
                'sources' => $result['sources'],
                'route' => $result['status'] === 'ok' ? $result['route'] : $result['status'],
            ]);
            $conversation->touch();

            $trace = $this->trace([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'message_id' => $answer->id,
            ] + $result, $started);

            $spent = $result['credits'];
            $this->credits->spend($user, $spent, $trace->id, 'Respuesta ('.$result['route'].')');

            if ($first && $cacheKey !== null && $result['status'] === 'ok' && $result['route'] !== 'cache' && ! $result['used_template']) {
                $this->cache->put($cacheKey, $locale, $checked['text'], $result['text'], $result['sources'], $result['model']);
            }

            return [
                'conversation' => $conversation,
                'question' => $asked,
                'answer' => $answer,
                'balance' => $this->credits->peek($user, $scope),
                'spent' => $spent,
            ];
        });
    }

    /**
     * The model's turn(s): tool calls run until it answers, the small model's
     * hand-over stops it so the large one starts afresh.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $docs
     * @param  list<string>  $context
     * @param  array<string, mixed>  $result
     * @return array{result: array<string, mixed>, text: string, docs: list<array<string, mixed>>, context: list<string>, escalate?: bool}
     */
    private function run(string $tier, bool $canEscalate, array $messages, array $docs, array $context, Scope $scope, Course $course, string $locale, array $result): array
    {
        $config = config("assistant.models.{$tier}");
        $request = [
            'tier' => $tier,
            'model' => (string) $config['id'],
            'effort' => (string) $config['effort'],
            'max_tokens' => (int) $config['max_tokens'],
            'fallbacks' => (bool) $config['fallbacks'],
            'system' => [['type' => 'text', 'text' => Prompt::make()->system(), 'cacheControl' => ['type' => 'ephemeral']]],
            'tools' => $this->tools->definitions($canEscalate),
            'messages' => $messages,
        ];
        $result['route'] = $tier;
        $maxRounds = (int) config('assistant.max_tool_rounds');
        $text = '';

        for ($round = 0; ; $round++) {
            try {
                $reply = $this->llm->send($request);
            } catch (LlmError $e) {
                // The large model busy or unreachable: the small one answers instead, once.
                if ($tier === 'large' && $e->retryable && ! in_array('fallback_small', $result['flags'], true)) {
                    $result['flags'][] = 'fallback_small';

                    return $this->run('small', false, $messages, $docs, $context, $scope, $course, $locale, $result);
                }

                return ['result' => ['status' => 'error', 'error' => $e->kind, 'text' => self::refusal('error', $locale), 'credits' => 0] + $result, 'text' => '', 'docs' => $docs, 'context' => $context];
            }

            $result['model'] = $reply->model;
            foreach ($reply->usage as $kind => $tokens) {
                $result['usage'][$kind] += $tokens;
            }
            $result['cost'] += self::price($tier, $reply->usage);

            if ($reply->stopReason === 'refusal') {
                return ['result' => ['status' => 'refusal', 'text' => self::refusal('refusal', $locale), 'credits' => 0] + $result, 'text' => '', 'docs' => $docs, 'context' => $context];
            }
            if ($reply->stopReason === 'max_tokens') {
                $result['flags'][] = 'max_tokens';
            }

            $calls = $reply->toolCalls();
            if ($reply->stopReason !== 'tool_use' || $calls === []) {
                $text = $reply->text();
                break;
            }
            if ($tier === 'small' && $canEscalate && in_array(Tools::ESCALATE, array_column($calls, 'name'), true)) {
                $result['tools'][] = ['name' => Tools::ESCALATE, 'ok' => true];

                return ['result' => $result, 'text' => '', 'docs' => $docs, 'context' => $context, 'escalate' => true];
            }
            if ($round >= $maxRounds) {
                $result['flags'][] = 'tool_rounds';
                $text = $reply->text();
                break;
            }

            $results = [];
            foreach ($calls as $call) {
                [$content, $error, $trace] = $this->execute($call, $docs, $scope, $course, $locale, $canEscalate);
                if ($call['name'] === Tools::TEMPLATE && ! $error) {
                    $result['used_template'] = true;
                }
                $result['tools'][] = $trace;
                $context[] = $content;
                $results[] = ['type' => 'tool_result', 'toolUseID' => $call['id'], 'content' => $content, 'isError' => $error];
            }
            $request['messages'][] = ['role' => 'assistant', 'content' => $reply->replay];
            $request['messages'][] = ['role' => 'user', 'content' => $results];
        }

        $result['chunks'] = array_values(array_map(fn (array $d) => ['id' => $d['id'], 'score' => $d['score']], $docs));

        return ['result' => $result, 'text' => $text, 'docs' => $docs, 'context' => $context];
    }

    /**
     * One tool call, with the student's rights.
     *
     * @param  array{id: string, name: string, input: array<string, mixed>}  $call
     * @param  list<array<string, mixed>>  $docs  the documents given so far; a search adds to them
     * @return array{0: string, 1: bool, 2: array<string, mixed>}
     */
    private function execute(array $call, array &$docs, Scope $scope, Course $course, string $locale, bool $canEscalate): array
    {
        switch ($call['name']) {
            case Tools::SEARCH:
                $query = mb_substr(trim((string) ($call['input']['consulta'] ?? '')), 0, 300);
                $found = $this->tools->search($query, $locale, $scope, array_column($docs, 'id'));
                if ($found === []) {
                    return ['Sin resultados en la parte del curso que el alumno puede leer.', false, ['name' => $call['name'], 'ok' => true, 'found' => 0]];
                }
                $from = count($docs) + 1;
                array_push($docs, ...$found);

                return [$this->documents($found, $from), false, ['name' => $call['name'], 'ok' => true, 'found' => count($found)]];

            case Tools::TEMPLATE:
                $run = $this->tools->template($call['input'], $course, $scope);

                return [$run['content'], $run['error'], ['name' => $call['name'], 'ok' => ! $run['error'], 'plantilla' => mb_substr((string) ($call['input']['plantilla'] ?? ''), 0, 40), 'cells' => count((array) ($call['input']['datos'] ?? []))]];

            case Tools::LEGAL:
                $run = $this->tools->legalParameters();

                return [$run['content'], false, ['name' => $call['name'], 'ok' => true]];

            default:
                return ['Esa herramienta no existe.', true, ['name' => mb_substr($call['name'], 0, 60), 'ok' => false]];
        }
    }

    /**
     * The question as the model receives it: who asks (language, modules),
     * the pieces of the course found for it, the question. The question is
     * the student's text, always inside its own tag, never instructions.
     *
     * @param  list<array<string, mixed>>  $docs
     */
    private function turn(string $question, string $locale, Scope $scope, Course $course, array $docs): string
    {
        $orders = $course->modules()->whereIn('id', $scope->modules)->orderBy('order')->get()
            ->map(fn ($m) => $m->isFinancials() ? 'Financials' : (string) $m->order)->implode(', ');
        $language = self::LANGUAGES[$locale] ?? 'español';

        return "<alumno idioma=\"{$language}\" modulos=\"".($scope->admin ? 'todos (administrador)' : ($orders === '' ? 'ninguno; solo las lecciones gratuitas' : $orders))."\"/>\n\n"
            .($docs === [] ? "<documentos>Ningún documento del curso coincide con la pregunta.</documentos>\n\n" : "<documentos>\n".$this->documents($docs, 1)."\n</documentos>\n\n")
            ."<pregunta>\n{$question}\n</pregunta>";
    }

    /** @param list<array<string, mixed>> $docs */
    private function documents(array $docs, int $from): string
    {
        $parts = [];
        foreach (array_values($docs) as $i => $doc) {
            $n = $from + $i;
            $title = str_replace('"', '\'', (string) $doc['title']);
            $parts[] = "<documento n=\"{$n}\" titulo=\"{$title}\">\n".trim((string) $doc['text'])."\n</documento>";
        }

        return implode("\n", $parts);
    }

    /**
     * [n] markers kept where they name a document given; the sources, each once, in the order first cited.
     *
     * @param  list<array<string, mixed>>  $docs
     * @return array{0: string, 1: list<array{n: int, title: string, path: ?string}>}
     */
    private function cite(string $text, array $docs): array
    {
        $sources = [];
        $text = (string) preg_replace_callback('/( ?)\[(\d{1,2}(?:\s*[,;]\s*\d{1,2})*)\](?!\()/', function (array $m) use ($docs, &$sources) {
            $kept = [];
            foreach (preg_split('/\s*[,;]\s*/', $m[2]) ?: [] as $n) {
                $doc = $docs[(int) $n - 1] ?? null;
                if ($doc === null) {
                    continue;
                }
                $kept[] = (int) $n;
                $sources[(int) $n] ??= ['n' => (int) $n, 'title' => (string) $doc['title'], 'path' => $doc['path']];
            }

            return $kept === [] ? '' : $m[1].'['.implode(', ', $kept).']';
        }, $text);

        return [trim($text), array_values($sources)];
    }

    /**
     * The conversation so far, as pairs the model answered: blocked and
     * failed turns are not history.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(AssistantConversation $conversation): array
    {
        $messages = $conversation->messages()->orderBy('id')->get(['role', 'content', 'route'])->all();
        $pairs = [];
        for ($i = 0; $i < count($messages) - 1; $i++) {
            if ($messages[$i]->role === 'user' && $messages[$i + 1]->role === 'assistant' && in_array($messages[$i + 1]->route, self::ANSWERED, true)) {
                $pairs[] = ['role' => 'user', 'content' => (string) $messages[$i]->content];
                $pairs[] = ['role' => 'assistant', 'content' => (string) $messages[$i + 1]->content];
                $i++;
            }
        }

        return array_slice($pairs, -max(2, (int) config('assistant.limits.history_messages')));
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function historyMessages(array $history): array
    {
        return array_map(fn (array $m) => [
            'role' => $m['role'],
            'content' => $m['role'] === 'user'
                ? '<pregunta>'.$this->guard->question($m['content'])['text'].'</pregunta>'
                // Earlier citations named documents this turn does not carry.
                : trim(preg_replace('/\s?\[\d{1,2}(?:\s*,\s*\d{1,2})*\]/', '', $m['content']) ?? $m['content']),
        ], $history);
    }

    /**
     * The trace of one question (the evaluation records its own through here).
     *
     * @param  array<string, mixed>  $data
     */
    public function trace(array $data, int $started): AssistantTrace
    {
        return AssistantTrace::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'route' => $data['route'],
            'status' => $data['status'],
            'model' => $data['model'] ?? null,
            'prompt_version' => Prompt::make()->version(),
            'index_version' => mb_substr(Indexer::version(), 0, 16),
            'input_tokens' => $data['usage']['input'] ?? 0,
            'output_tokens' => $data['usage']['output'] ?? 0,
            'cache_read_tokens' => $data['usage']['cache_read'] ?? 0,
            'cache_write_tokens' => $data['usage']['cache_write'] ?? 0,
            'cost_usd' => round((float) ($data['cost'] ?? 0), 6),
            'credits' => (int) ($data['credits'] ?? 0),
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'chunks' => $data['chunks'] ?? [],
            'tools' => $data['tools'] ?? [],
            'flags' => array_values(array_unique($data['flags'] ?? [])),
            'error' => isset($data['error']) ? mb_substr((string) $data['error'], 0, 255) : null,
        ]);
    }

    /** @param array{input: int, output: int, cache_read: int, cache_write: int} $usage */
    public static function price(string $tier, array $usage): float
    {
        $price = config("assistant.models.{$tier}.price");

        return ($usage['input'] * $price['input'] + $usage['output'] * $price['output']
            + $usage['cache_read'] * $price['cache_read'] + $usage['cache_write'] * $price['cache_write']) / 1_000_000;
    }

    /** The answer when the guard, the model or the network says no, in the student's language. */
    public static function refusal(string $why, string $locale): string
    {
        return match ($why) {
            'injection', 'prompt' => __('I can only help with the course, and I keep how I work to myself. Ask me about the course and I will gladly help.', [], $locale),
            'secrets' => __('I have no access to keys or to the platform\'s settings, and could not share them. Ask me about the course.', [], $locale),
            'accounts' => __('I cannot see anyone\'s account, orders, invoices or personal data, not even yours. For your account, go to My account or write to support.', [], $locale),
            'answer' => __('I cannot give that answer. Ask me in another way about the course.', [], $locale),
            'refusal' => __('I cannot help with that. Ask me about the course: a phase, a document, a tax, a template.', [], $locale),
            default => __('I could not answer just now. Try again in a few minutes; no credits were used.', [], $locale),
        };
    }

    /** A message as the API returns it: its text, its HTML (marked with the reader's fingerprint), its sources. */
    public static function present(AssistantMessage $message, User $user): array
    {
        $html = $message->role === 'assistant' ? Markdown::toHtml($message->content, glossary: true) : null;

        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'html' => $html === null ? null : ContentFingerprint::stamp($html, $user),
            'sources' => $message->sources ?? [],
            'route' => $message->route,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
