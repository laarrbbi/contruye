<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Despacho '.fake()->lastName(),
            'category' => fake()->randomElement(Partner::CATEGORIES),
            'description_es' => 'Asesoramiento a promotores inmobiliarios.',
            'description_en' => null,
            'website' => 'https://example.es',
            'phone' => '91'.fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'is_active' => true,
            'is_national' => false,
        ];
    }

    /** Covers every province (bank, insurer, online service). */
    public function national(): static
    {
        return $this->state(fn () => ['is_national' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
