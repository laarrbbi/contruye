<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude, through the official PHP SDK (anthropic-ai/sdk). The key is read
 * from config/assistant.php on the server and handed to the SDK only: it is
 * never logged and never part of an error message.
 *
 * - The system prompt and the tools are the stable prefix, cached (prompt
 *   caching, the breakpoint on the last system block); the question and the
 *   pieces of the course retrieved for it come after. A tier that asks for
 *   it (`cache_turn`) also caches the conversation as it grows, with the
 *   top-level automatic breakpoint: its tool rounds re-read what they sent
 *   before instead of paying for it again.
 * - The medium and large models (Claude Sonnet 5.5, Claude Opus 5.5) opt
 *   into server-side fallbacks (`fallbacks: "default"`, beta
 *   server-side-fallback-2026-07-01): when a safety classifier declines, the
 *   API retries on the model Anthropic recommends. The small one (Claude
 *   Haiku 5.5) has none: its refusal comes back as stop reason «refusal» and
 *   the assistant says so.
 * - A tier that has spent its tool rounds is asked once more with
 *   `tool_choice: none`: it answers with what it gathered, and no call ends in
 *   a tool request nobody runs.
 * - Thinking is adaptive (the default on the three models); effort sets its
 *   depth, and is the same on every request of a tier (a change of effort
 *   would start its cache over).
 */
final class AnthropicLlm implements Llm
{
    private ?Client $client = null;

    public function send(array $request): LlmReply
    {
        $params = [
            'maxTokens' => $request['max_tokens'],
            'messages' => $request['messages'],
            'model' => $request['model'],
            'outputConfig' => ['effort' => $request['effort']],
            'system' => $request['system'],
            'tools' => $request['tools'] === [] ? null : $request['tools'],
        ];
        // Automatic caching of the conversation so far (one of the four breakpoints): the next tool
        // round re-reads the question and its documents instead of paying for them again.
        if ($request['cache_turn'] ?? false) {
            $params['cacheControl'] = ['type' => 'ephemeral'];
        }
        // The tier's last round: the tools stay defined (the conversation holds their calls, and the
        // cached prefix is the same) but the model may not call one, so it answers with what it has.
        if ($request['tools_off'] ?? false) {
            $params['toolChoice'] = ['type' => 'none'];
        }
        if ($request['fallbacks']) {
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        try {
            $response = $this->client()->beta->messages->create(...$params);
        } catch (AuthenticationException) {
            throw new LlmError('The assistant\'s API key was refused.', false, 'auth');
        } catch (BadRequestException $e) {
            throw new LlmError('The request to the model was refused: '.$e->type?->value, false, 'bad_request');
        } catch (RateLimitException) {
            throw new LlmError('The model is rate limited.', true, 'rate_limit');
        } catch (APIStatusException $e) {
            throw new LlmError('The model answered with an error: '.($e->type?->value ?? 'unknown'), true, 'status');
        } catch (APIConnectionException) {
            throw new LlmError('The model could not be reached.', true, 'connection');
        }

        $blocks = [];
        foreach ($response->content as $block) {
            $blocks[] = match ($block->type) {
                'text' => ['type' => 'text', 'text' => $block->text],
                'tool_use' => ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $block->input],
                default => ['type' => $block->type],
            };
        }

        return new LlmReply(
            blocks: $blocks,
            replay: $response->content,
            stopReason: $response->stopReason,
            model: $response->model,
            usage: [
                'input' => $response->usage->inputTokens,
                'output' => $response->usage->outputTokens,
                'cache_read' => (int) ($response->usage->cacheReadInputTokens ?? 0),
                'cache_write' => (int) ($response->usage->cacheCreationInputTokens ?? 0),
            ],
        );
    }

    private function client(): Client
    {
        $key = (string) config('assistant.anthropic.api_key');
        if ($key === '') {
            throw new LlmError('The assistant has no API key.', false, 'no_key');
        }

        return $this->client ??= new Client(
            apiKey: $key,
            requestOptions: [
                'timeout' => (float) config('assistant.anthropic.timeout'),
                'maxRetries' => (int) config('assistant.anthropic.max_retries'),
            ],
        );
    }
}
