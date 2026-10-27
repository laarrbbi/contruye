<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course review left by a student who actually bought the course.
 *
 * Reviews are written by real buyers only (enforced in the controller) and
 * stay `pending` until an admin approves them — only approved reviews are
 * ever public. There is deliberately no mechanism to author a review on a
 * student's behalf: the public rating must reflect real customers.
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'rating',
        'body',
        'status',
        'admin_note',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
