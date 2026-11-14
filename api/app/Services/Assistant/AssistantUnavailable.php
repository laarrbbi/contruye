<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * The assistant does not take this question: not configured, not open yet,
 * not enrolled, the day's budget spent, no credits left, a question already
 * running. The reason is a code the API and the page translate.
 */
final class AssistantUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /** The HTTP status the API answers with. */
    public function status(): int
    {
        return match ($this->reason) {
            'no_credits' => 402,
            'busy' => 409,
            'not_enrolled' => 403,
            default => 503,
        };
    }
}
