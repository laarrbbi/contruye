<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The ITP and AJD of an autonomous community, as the templates apply them to
 * a site in it (fractions: 0.1 = 10 %). `note` says what the general rate
 * leaves out (brackets, reduced rates, IGIC or IPSI).
 */
class LegalRegionRate extends Model
{
    protected $fillable = ['itp', 'ajd'];

    protected function casts(): array
    {
        return [
            'itp' => 'float',
            'ajd' => 'float',
            'order' => 'integer',
        ];
    }
}
