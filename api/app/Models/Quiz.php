<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A module quiz or the final exam. `questions` is the instructor's JSON as
 * delivered: [{numero, pregunta, opciones: [{letra, texto, correcta}]}].
 */
class Quiz extends Model
{
    use HasFactory, HasLocalizedAttributes;

    public const KINDS = ['module', 'final'];

    protected $fillable = [
        'course_id',
        'module_id',
        'kind',
        'title_es',
        'title_en',
        'pass_mark',
        'duration_minutes',
        'questions',
    ];

    protected function casts(): array
    {
        return [
            'pass_mark' => 'integer',
            'duration_minutes' => 'integer',
            'questions' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localizedValue('title', $locale);
    }

    public function isFinal(): bool
    {
        return $this->kind === 'final';
    }

    public function questionCount(): int
    {
        return count($this->questions ?? []);
    }

    /**
     * The questions as a student may see them before answering: the
     * `correcta` flag is stripped from every option.
     *
     * @return list<array{numero: int, pregunta: string, opciones: list<array{letra: string, texto: string}>}>
     */
    public function questionsForStudent(): array
    {
        return array_map(fn (array $question) => [
            'numero' => $question['numero'],
            'pregunta' => $question['pregunta'],
            'opciones' => array_map(
                fn (array $option) => ['letra' => $option['letra'], 'texto' => $option['texto']],
                $question['opciones'],
            ),
        ], $this->questions ?? []);
    }

    /**
     * The letter of the correct option of each question, keyed by its number.
     *
     * @return array<int, string>
     */
    public function answerKey(): array
    {
        $key = [];
        foreach ($this->questions ?? [] as $question) {
            foreach ($question['opciones'] as $option) {
                if ($option['correcta'] === true) {
                    $key[(int) $question['numero']] = $option['letra'];
                }
            }
        }

        return $key;
    }
}
