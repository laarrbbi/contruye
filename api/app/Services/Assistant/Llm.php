<?php

namespace App\Services\Assistant;

/**
 * The one door to the language model. The real one is AnthropicLlm (the
 * official SDK, the key on the server); the tests bind a fake, so no test
 * ever reaches the network or spends a cent.
 */
interface Llm
{
    /**
     * @param  array{tier: string, model: string, effort: string, max_tokens: int, fallbacks: bool, system: list<array<string, mixed>>, tools: list<array<string, mixed>>, messages: list<array<string, mixed>>}  $request
     *
     * @throws LlmError
     */
    public function send(array $request): LlmReply;
}
