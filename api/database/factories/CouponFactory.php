<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'percent_off' => fake()->randomElement([10, 20, 30, 50]),
            'max_uses' => 100,
            'uses' => 0,
            'expires_at' => now()->addMonths(3),
        ];
    }
}
