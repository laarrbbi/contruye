<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderModule extends Model
{
    protected $fillable = [
        'order_id',
        'module_id',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
