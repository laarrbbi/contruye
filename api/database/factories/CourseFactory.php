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
            'title_fr' => $title,
            'title_ar' => 'تكوين '.fake()->unique()->word(),
            'description_fr' => fake()->paragraph(),
            'description_ar' => 'وصف التكوين بالعربية.',
            'price_dzd' => fake()->numberBetween(15000, 90000),
            'price_eur' => null,
            'status' => 'published',
            'cover_image' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
