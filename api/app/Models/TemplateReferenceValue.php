<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateReferenceValue extends Model
{
    protected $fillable = ['template', 'sheet', 'cell', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'float'];
    }
}
