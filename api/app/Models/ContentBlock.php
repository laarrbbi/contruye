<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tagged block of course content, attached to a module or a lesson.
 *
 * Types mirror the tags used in the source programme:
 * - `legal`        → [BASE LEGALE], the statutory references behind the lesson
 * - `partner_slot` → [PARTENAIRE A INTEGRER], a provider slot. It renders a
 *                    placeholder until real partners are attached to the
 *                    module; contacts are NEVER invented.
 * - `expert_tip`   → CONSEIL D'OR DE L'EXPERT, highlighted practitioner advice
 * - `text`         → plain prose
 * - `overview`     → a module section that comes before its lessons, as the
 *                    instructor wrote it ("Leyes que aplican", "Dónde ir"):
 *                    Markdown with its own "## " heading
 * - `section`      → the same, for a section after the lessons ("Partners en
 *                    tu provincia")
 */
class ContentBlock extends Model
{
    use HasFactory, HasLocalizedAttributes;

    public const TYPES = ['text', 'legal', 'partner_slot', 'expert_tip', 'overview', 'section'];

    protected $fillable = [
        'module_id',
        'lesson_id',
        'type',
        'order',
        'body_es',
        'body_en',
        'partner_category',
        'needs_validation',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'needs_validation' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Falls back to Spanish while the English translation is pending. */
    public function localizedBody(?string $locale = null): ?string
    {
        return $this->localizedValue('body', $locale);
    }
}
