<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'order' => fake()->numberBetween(1, 20),
            'title_es' => 'Module — '.fake()->sentence(3),
            'title_en' => 'الوحدة — '.fake()->word(),
        ];
    }
}
