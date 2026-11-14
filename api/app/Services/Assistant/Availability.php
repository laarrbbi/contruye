<?php

namespace App\Services\Assistant;

use App\Models\AssistantEvalRun;
use App\Models\User;
use App\Services\LegalParameters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Whether the assistant answers, and to whom.
 *
 * - Without an API key it answers no one (`not_configured`).
 * - An admin may use it as soon as the key is set: that is how the owner
 *   tries it before it opens.
 * - A student gets it only when it is switched on (ASSISTANT_ENABLED), the
 *   evaluation set has passed for this exact version (prompt, models,
 *   index), the student is enrolled, and the day's budget is not spent.
 *
 * The version key is what the evaluation gate and the answer cache follow:
 * a new prompt wording, another model or a rebuilt index is a new version,
 * which students get only once `php artisan assistant:eval` passes for it.
 */
final class Availability
{
    public function configured(): bool
    {
        return trim((string) config('assistant.anthropic.api_key')) !== '';
    }

    public function enabled(): bool
    {
        return (bool) config('assistant.enabled');
    }

    /** Open to students: switched on, a key, an evaluation passed for this version. */
    public function launched(): bool
    {
        return $this->enabled() && $this->configured() && $this->evalPassed();
    }

    /**
     * @return array{available: bool, reason: ?string}
     */
    public function for(User $user, Scope $scope): array
    {
        if (! $this->configured()) {
            return ['available' => false, 'reason' => 'not_configured'];
        }
        if ($user->isAdmin()) {
            return ['available' => true, 'reason' => null];
        }
        if (! $this->enabled() || ! $this->evalPassed()) {
            return ['available' => false, 'reason' => 'coming_soon'];
        }
        if (! $scope->eligible()) {
            return ['available' => false, 'reason' => 'not_enrolled'];
        }
        if ($this->spentToday() >= (float) config('assistant.daily_budget_usd')) {
            return ['available' => false, 'reason' => 'paused'];
        }

        return ['available' => true, 'reason' => null];
    }

    public function versionKey(): string
    {
        return hash('sha256', implode('|', [
            Prompt::make()->version(),
            config('assistant.models.small.id'), config('assistant.models.small.effort'),
            config('assistant.models.large.id'), config('assistant.models.large.effort'),
            Indexer::version(),
        ]));
    }

    /** The models as a trace and an evaluation run name them. */
    public static function models(): string
    {
        return config('assistant.models.small.id').'/'.config('assistant.models.small.effort')
            .' + '.config('assistant.models.large.id').'/'.config('assistant.models.large.effort');
    }

    public function evalPassed(): bool
    {
        return AssistantEvalRun::query()->where('version_key', $this->versionKey())->where('passed', true)->exists();
    }

    public function lastEval(): ?AssistantEvalRun
    {
        return AssistantEvalRun::query()->where('version_key', $this->versionKey())->latest('ran_at')->latest('id')->first();
    }

    /** What the platform has spent today, Madrid's day, in US dollars. */
    public function spentToday(): float
    {
        $start = CarbonImmutable::now(config('app.business_timezone'))->startOfDay()->utc();

        return (float) DB::table('assistant_traces')->where('created_at', '>=', $start)->sum('cost_usd');
    }

    /** The legal parameters in force, as a version: an answer quoting a rate is not served once the rate changes. */
    public function legalVersion(): string
    {
        return substr(hash('sha256', json_encode(app(LegalParameters::class)->forEngine())), 0, 16);
    }
}
