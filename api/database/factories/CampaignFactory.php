<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'label_es' => 'Offre de lancement',
            'label_en' => 'عرض الإطلاق',
            'percent_off' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(5),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
