<?php

namespace Tests\Support;

use App\Services\Assistant\Llm;
use App\Services\Assistant\LlmError;
use App\Services\Assistant\LlmReply;
use Closure;

/**
 * The model, faked: replies queued in order (a reply, an error to throw, or
 * a closure given the request), every request kept for the test to read.
 * No test reaches the network or spends anything.
 */
final class FakeLlm implements Llm
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @var list<LlmReply|LlmError|Closure> */
    private array $queue = [];

    public function push(LlmReply|LlmError|Closure ...$replies): self
    {
        array_push($this->queue, ...$replies);

        return $this;
    }

    public function send(array $request): LlmReply
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? self::text('Según el curso, se pide en el Registro de la Propiedad [1].');
        if ($next instanceof Closure) {
            $next = $next($request);
        }
        if ($next instanceof LlmError) {
            throw $next;
        }

        return $next;
    }

    public static function text(string $text, string $model = 'claude-haiku-5-5', string $stop = 'end_turn'): LlmReply
    {
        $blocks = [['type' => 'text', 'text' => $text]];

        return new LlmReply($blocks, $blocks, $stop, $model, ['input' => 1000, 'output' => 200, 'cache_read' => 3000, 'cache_write' => 0]);
    }

    /** @param array<string, mixed> $input */
    public static function tool(string $name, array $input, string $id = 'toolu_01'): LlmReply
    {
        $blocks = [['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]];

        return new LlmReply($blocks, $blocks, 'tool_use', 'claude-haiku-5-5', ['input' => 900, 'output' => 50, 'cache_read' => 3000, 'cache_write' => 0]);
    }

    public static function refusal(): LlmReply
    {
        return new LlmReply([], [], 'refusal', 'claude-haiku-5-5', ['input' => 900, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0]);
    }

    /** The text of the last user turn of a request (the question with its documents, or tool results). */
    public static function lastUserText(array $request): string
    {
        $last = end($request['messages']);
        if (is_string($last['content'])) {
            return $last['content'];
        }

        return implode("\n", array_map(fn ($block) => (string) ($block['content'] ?? $block['text'] ?? ''), $last['content']));
    }
}
