<?php

namespace Database\Factories;

use App\Models\CustomTask;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomTask>
 */
class CustomTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_id' => Module::factory(),
            'body' => 'Llamar a '.fake()->lastName().' para el presupuesto.',
            'is_done' => false,
            'order' => 1,
        ];
    }
}
