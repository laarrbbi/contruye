<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quiz in the package's format: {numero, pregunta, opciones: [{letra,
 * texto, correcta}]}. The right answer of question n is option B.
 *
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'module_id' => null,
            'kind' => 'module',
            'title_es' => 'Quiz · 4 preguntas · mínimo 3/4',
            'title_en' => null,
            'pass_mark' => 3,
            'duration_minutes' => null,
            'questions' => array_map(fn (int $n) => [
                'numero' => $n,
                'pregunta' => "Pregunta {$n}",
                'opciones' => array_map(fn (string $letter) => [
                    'letra' => $letter,
                    'texto' => "Opción {$letter} de la pregunta {$n}",
                    'correcta' => $letter === 'B',
                ], ['A', 'B', 'C', 'D']),
            ], range(1, 4)),
        ];
    }

    public function forModule(Module $module): static
    {
        return $this->state(fn () => ['course_id' => $module->course_id, 'module_id' => $module->id, 'kind' => 'module']);
    }

    public function finalExam(Course $course): static
    {
        return $this->state(fn () => [
            'course_id' => $course->id,
            'module_id' => null,
            'kind' => 'final',
            'title_es' => 'Examen final',
            'duration_minutes' => 60,
        ]);
    }
}
