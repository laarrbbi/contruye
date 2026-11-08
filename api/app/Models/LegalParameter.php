<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the six rates the law fixes in the templates' «Parámetros legales»
 * sheet (IVA general and reduced, IPS, CLEA, legal interest, AJD on the
 * loan). `value` is a fraction, as the engine takes it (0.21 = 21 %).
 */
class LegalParameter extends Model
{
    protected $fillable = ['value'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'original_value' => 'float',
            'order' => 'integer',
        ];
    }
}
