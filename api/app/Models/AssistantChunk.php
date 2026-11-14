<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A piece of the course, in one language, with who may read it (the assistant's index). */
class AssistantChunk extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
