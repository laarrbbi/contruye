<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A run of the evaluation set for one version of the prompt, the models and the index. */
class AssistantEvalRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'report' => 'array', 'score' => 'float', 'ran_at' => 'datetime'];
    }
}
