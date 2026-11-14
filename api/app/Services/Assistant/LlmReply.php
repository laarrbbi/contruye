<?php

namespace App\Services\Assistant;

/**
 * A model's reply, as the assistant reads it: its text and tool calls, the
 * blocks to send back unchanged on the next round (thinking included), why it
 * stopped, the model that answered (another one after a server-side
 * fallback) and the tokens it counted.
 */
final class LlmReply
{
    /**
     * @param  list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}>  $blocks
     * @param  list<mixed>  $replay
     * @param  array{input: int, output: int, cache_read: int, cache_write: int}  $usage
     */
    public function __construct(
        public readonly array $blocks,
        public readonly array $replay,
        public readonly ?string $stopReason,
        public readonly string $model,
        public readonly array $usage,
    ) {}

    public function text(): string
    {
        return trim(implode("\n\n", array_map(
            fn (array $block) => (string) ($block['text'] ?? ''),
            array_filter($this->blocks, fn (array $block) => $block['type'] === 'text'),
        )));
    }

    /** @return list<array{id: string, name: string, input: array<string, mixed>}> */
    public function toolCalls(): array
    {
        return array_values(array_map(
            fn (array $block) => ['id' => (string) $block['id'], 'name' => (string) $block['name'], 'input' => (array) ($block['input'] ?? [])],
            array_filter($this->blocks, fn (array $block) => $block['type'] === 'tool_use'),
        ));
    }
}
