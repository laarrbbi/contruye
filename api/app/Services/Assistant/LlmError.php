<?php

namespace App\Services\Assistant;

use RuntimeException;

/** The model could not answer: rate limit, overload, network, a bad request. Never carries the key. */
final class LlmError extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly string $kind = 'error')
    {
        parent::__construct($message);
    }
}
