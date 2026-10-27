<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'coupon_id' => null,
            'amount' => fake()->numberBetween(15000, 90000),
            'currency' => 'DZD',
            'method' => fake()->randomElement(['chargily', 'manual_ccp', 'manual_baridimob']),
            'status' => 'pending',
            'chargily_checkout_id' => null,
            'receipt_path' => null,
            'admin_note' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid', 'paid_at' => now()]);
    }

    public function awaitingReview(): static
    {
        return $this->state(fn () => [
            'status' => 'awaiting_review',
            'method' => 'manual_ccp',
            'receipt_path' => 'receipts/fake-receipt.jpg',
            'receipt_uploaded_at' => now(),
        ]);
    }

    public function chargily(): static
    {
        return $this->state(fn () => [
            'method' => 'chargily',
            'chargily_checkout_id' => '01'.fake()->regexify('[a-z0-9]{24}'),
        ]);
    }
}
