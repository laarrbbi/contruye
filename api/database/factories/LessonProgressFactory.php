<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'completed_at' => null,
            'last_position_seconds' => fake()->numberBetween(0, 1800),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
