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
            'title_en' => 'تكوين '.fake()->unique()->word(),
            'description_es' => fake()->paragraph(),
            'description_en' => 'وصف التكوين بالعربية.',
            'price_eur' => fake()->numberBetween(15000, 90000),
            'status' => 'published',
            'cover_image' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
