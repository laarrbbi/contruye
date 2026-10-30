<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'order' => 1,
            'text_es' => 'Presente la documentación '.fake()->word().' en el registro del ayuntamiento.',
            'text_en' => null,
        ];
    }
}
