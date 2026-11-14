<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of the credits ledger: the monthly allowance, a spend, a refund,
 * a grant. Append-only, like the invoices (constitution IV): a balance is
 * the sum of its lines, and a mistake is corrected by a new line.
 */
class AssistantCreditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'assistant_credit_ledger';

    protected $fillable = ['user_id', 'delta', 'reason', 'period', 'trace_id', 'note'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The credits ledger is append-only.'));
        static::deleting(fn () => throw new LogicException('The credits ledger is append-only.'));
    }
}
