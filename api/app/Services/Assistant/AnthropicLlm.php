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
 *   pieces of the course retrieved for it come after.
 * - The large model (Claude Opus 5.5) opts into server-side fallbacks
 *   (`fallbacks: "default"`, beta server-side-fallback-2026-07-01): when a
 *   safety classifier declines, the API retries on the model Anthropic
 *   recommends. The small one (Claude Haiku 5.5) has none: its refusal comes
 *   back as stop reason «refusal» and the assistant says so.
 * - Thinking is adaptive (the default on both models); effort sets its depth.
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
