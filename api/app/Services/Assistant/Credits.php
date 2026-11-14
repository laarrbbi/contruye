<?php

namespace App\Services\Assistant;

use App\Models\AssistantCreditEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The credits: a monthly allowance by plan (config assistant.credits), as
 * Claude, ChatGPT or Lovable count them. The month is Europe/Madrid's; what
 * is not spent does not carry over. The ledger is append-only: the month's
 * allowance is a line, written the first time the student asks in that month
 * (and topped up the same month if the student moves to a larger plan);
 * each answer is a line; an admin's grant is a line. A balance is the sum.
 */
final class Credits
{
    public static function period(): string
    {
        return CarbonImmutable::now(config('app.business_timezone'))->format('Y-m');
    }

    /** When the next allowance arrives: the first of next month, Madrid time. */
    public static function resetsAt(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.business_timezone'))->startOfMonth()->addMonth();
    }

    public static function allowance(string $tier): int
    {
        return (int) config("assistant.credits.monthly.{$tier}", 0);
    }

    /** The cost of an answer by the given model tier. */
    public static function cost(string $tier): int
    {
        return (int) config("assistant.models.{$tier}.credits");
    }

    /** The balance this month, with this month's allowance written first if it is not yet. */
    public function balance(User $user, Scope $scope): int
    {
        $this->grantMonthly($user, $scope);

        return $this->sum($user);
    }

    /** The balance as it stands, writing nothing (for a page that only shows it). */
    public function peek(User $user, Scope $scope): int
    {
        $granted = (int) AssistantCreditEntry::query()->where('user_id', $user->id)
            ->where('period', self::period())->where('reason', 'monthly')->sum('delta');

        return $this->sum($user) + max(0, self::allowance($scope->tier()) - $granted);
    }

    public function spend(User $user, int $credits, ?int $traceId, string $note): void
    {
        if ($credits <= 0) {
            return;
        }
        AssistantCreditEntry::query()->create([
            'user_id' => $user->id,
            'delta' => -$credits,
            'reason' => 'spend',
            'period' => self::period(),
            'trace_id' => $traceId,
            'note' => mb_substr($note, 0, 160),
        ]);
    }

    /** An admin's grant (or, negative, a correction) for this month. */
    public function grant(User $user, int $credits, string $note): AssistantCreditEntry
    {
        return AssistantCreditEntry::query()->create([
            'user_id' => $user->id,
            'delta' => $credits,
            'reason' => 'grant',
            'period' => self::period(),
            'note' => mb_substr($note, 0, 160),
        ]);
    }

    private function grantMonthly(User $user, Scope $scope): void
    {
        $period = self::period();
        $allowance = self::allowance($scope->tier());

        DB::transaction(function () use ($user, $period, $allowance) {
            // One request at a time per student writes the allowance (MySQL row lock; SQLite serialises writes).
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);

            $granted = (int) AssistantCreditEntry::query()->where('user_id', $user->id)
                ->where('period', $period)->where('reason', 'monthly')->sum('delta');
            if ($granted < $allowance) {
                AssistantCreditEntry::query()->create([
                    'user_id' => $user->id,
                    'delta' => $allowance - $granted,
                    'reason' => 'monthly',
                    'period' => $period,
                    'note' => $granted === 0 ? 'Asignación mensual' : 'Ajuste de la asignación mensual al nuevo plan',
                ]);
            }
        });
    }

    private function sum(User $user): int
    {
        return (int) AssistantCreditEntry::query()->where('user_id', $user->id)->where('period', self::period())->sum('delta');
    }
}
