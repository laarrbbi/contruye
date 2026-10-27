<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();

        $early20 = Coupon::query()->firstOrCreate(
            ['code' => 'EARLY20'],
            ['percent_off' => 20, 'max_uses' => 100, 'uses' => 0, 'expires_at' => now()->addMonths(2)],
        );

        $obra50 = Coupon::query()->firstOrCreate(
            ['code' => 'OBRA50'],
            ['percent_off' => 50, 'max_uses' => 10, 'uses' => 0, 'expires_at' => null],
        );

        $pablo = User::query()->firstOrCreate(
            ['email' => 'pablo@example.es'],
            [
                'name' => 'Pablo Ruiz',
                'phone' => '634567890',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'es',
                'email_verified_at' => now(),
            ],
        );

        $marta = User::query()->firstOrCreate(
            ['email' => 'marta@example.es'],
            [
                'name' => 'Marta Gil',
                'phone' => '645678901',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        // Pending card checkout (started, never completed).
        Order::query()->firstOrCreate(
            ['user_id' => $pablo->id, 'course_id' => $course->id, 'method' => 'stripe', 'status' => 'pending'],
            [
                'amount' => 490,
                'currency' => 'EUR',
                'stripe_session_id' => 'cs_test_'.Str::random(24),
            ],
        );

        // Failed card payment (the Stripe session expired).
        Order::query()->firstOrCreate(
            ['user_id' => $marta->id, 'course_id' => $course->id, 'method' => 'stripe', 'status' => 'failed'],
            [
                'amount' => $early20->apply(490),
                'currency' => 'EUR',
                'coupon_id' => $early20->id,
                'stripe_session_id' => 'cs_test_'.Str::random(24),
            ],
        );

        // Refunded bank-transfer order, with the admin's note.
        Order::query()->firstOrCreate(
            ['user_id' => $pablo->id, 'course_id' => $course->id, 'method' => 'manual_transfer', 'status' => 'refunded'],
            [
                'amount' => 490,
                'currency' => 'EUR',
                'receipt_path' => 'receipts/demo/pablo-transferencia.jpg',
                'admin_note' => 'Pago duplicado — devuelto por transferencia el '.now()->subDays(5)->format('d/m/Y').'.',
                'paid_at' => now()->subDays(10),
            ],
        );

        // Cash payment (in-office), discounted with OBRA50, paid + enrolled
        // through the one sanctioned path.
        $cashOrder = Order::query()->firstOrCreate(
            ['user_id' => $marta->id, 'course_id' => $course->id, 'method' => 'cash'],
            [
                'amount' => $obra50->apply(490),
                'currency' => 'EUR',
                'coupon_id' => $obra50->id,
                'status' => 'pending',
                'admin_note' => 'Pagado en efectivo en la oficina — descuento OBRA50 aplicado.',
            ],
        );

        if ($cashOrder->status !== 'paid') {
            $cashOrder->markPaidAndEnroll();
        }
    }
}
