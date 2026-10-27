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
            'name' => 'Cabinet '.fake()->lastName(),
            'category' => fake()->randomElement(Partner::CATEGORIES),
            'description_fr' => 'Accompagnement des promoteurs immobiliers.',
            'description_ar' => 'مرافقة المرقّين العقاريين.',
            'website' => 'https://example.dz',
            'phone' => '0550'.fake()->numerify('######'),
            'email' => fake()->safeEmail(),
            'is_active' => true,
            'is_national' => false,
        ];
    }

    /** Covers every wilaya (bank, insurer, online service). */
    public function national(): static
    {
        return $this->state(fn () => ['is_national' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
