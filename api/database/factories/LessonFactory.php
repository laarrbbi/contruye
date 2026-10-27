<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'order' => fake()->numberBetween(1, 20),
            'title_fr' => 'Leçon — '.fake()->sentence(4),
            'title_ar' => 'الدرس — '.fake()->word(),
            'bunny_video_id' => fake()->uuid(),
            'duration_seconds' => fake()->numberBetween(600, 2400),
            'is_free_preview' => false,
            'attachments' => null,
        ];
    }

    public function freePreview(): static
    {
        return $this->state(fn () => ['is_free_preview' => true]);
    }
}
