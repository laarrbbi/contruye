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
            'amount' => fake()->numberBetween(150, 900),
            'currency' => 'EUR',
            'method' => fake()->randomElement(['stripe', 'manual_transfer', 'manual_bizum']),
            'status' => 'pending',
            'stripe_session_id' => null,
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
            'method' => 'manual_transfer',
            'receipt_path' => 'receipts/fake-receipt.jpg',
            'receipt_uploaded_at' => now(),
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn () => [
            'method' => 'stripe',
            'stripe_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }
}
