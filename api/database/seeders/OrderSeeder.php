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
        $course = Course::query()->where('slug', 'promotion-immobiliere-algerie')->firstOrFail();

        $early20 = Coupon::query()->firstOrCreate(
            ['code' => 'EARLY20'],
            ['percent_off' => 20, 'max_uses' => 100, 'uses' => 0, 'expires_at' => now()->addMonths(2)],
        );

        $bled50 = Coupon::query()->firstOrCreate(
            ['code' => 'BLED50'],
            ['percent_off' => 50, 'max_uses' => 10, 'uses' => 0, 'expires_at' => null],
        );

        $yacine = User::query()->firstOrCreate(
            ['email' => 'yacine@example.dz'],
            [
                'name' => 'Yacine Hamidi',
                'phone' => '0770123456',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'fr',
                'email_verified_at' => now(),
            ],
        );

        $lina = User::query()->firstOrCreate(
            ['email' => 'lina@example.dz'],
            [
                'name' => 'Lina Mansouri',
                'phone' => '0560123457',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'ar',
                'email_verified_at' => now(),
            ],
        );

        // Pending Chargily checkout (started, never completed).
        Order::query()->firstOrCreate(
            ['user_id' => $yacine->id, 'course_id' => $course->id, 'method' => 'chargily', 'status' => 'pending'],
            [
                'amount' => 90000,
                'currency' => 'DZD',
                'chargily_checkout_id' => '01'.Str::lower(Str::random(24)),
            ],
        );

        // Failed Chargily payment.
        Order::query()->firstOrCreate(
            ['user_id' => $lina->id, 'course_id' => $course->id, 'method' => 'chargily', 'status' => 'failed'],
            [
                'amount' => $early20->apply(90000),
                'currency' => 'DZD',
                'coupon_id' => $early20->id,
                'chargily_checkout_id' => '01'.Str::lower(Str::random(24)),
            ],
        );

        // Refunded manual CCP order, with the admin's note.
        Order::query()->firstOrCreate(
            ['user_id' => $yacine->id, 'course_id' => $course->id, 'method' => 'manual_ccp', 'status' => 'refunded'],
            [
                'amount' => 90000,
                'currency' => 'DZD',
                'receipt_path' => 'receipts/demo/yacine-ccp.jpg',
                'admin_note' => 'Double paiement détecté — remboursé par mandat CCP le '.now()->subDays(5)->format('d/m/Y').'.',
                'paid_at' => now()->subDays(10),
            ],
        );

        // Cash payment (in-office), discounted with BLED50, paid + enrolled
        // through the one sanctioned path.
        $cashOrder = Order::query()->firstOrCreate(
            ['user_id' => $lina->id, 'course_id' => $course->id, 'method' => 'cash'],
            [
                'amount' => $bled50->apply(90000),
                'currency' => 'DZD',
                'coupon_id' => $bled50->id,
                'status' => 'pending',
                'admin_note' => 'Réglé en espèces au bureau (Alger) — remise BLED50 appliquée.',
            ],
        );

        if ($cashOrder->status !== 'paid') {
            $cashOrder->markPaidAndEnroll();
        }
    }
}
