<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title),
            'title_es' => $title,
            // English left blank, as the real course is seeded: the material
            // is authored in Spanish and an English reader falls back to it.
            // A test about English sets it.
            'title_en' => null,
            'description_es' => fake()->paragraph(),
            'description_en' => null,
            'price_eur' => fake()->numberBetween(150, 900),
            'status' => 'published',
            'cover_image' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
