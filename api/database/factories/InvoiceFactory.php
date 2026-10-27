<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 90000);

        return [
            'order_id' => Order::factory(),
            'number' => sprintf('FAC-2026-%05d', $sequence),
            'year' => 2026,
            'sequence' => $sequence,
            'issued_at' => now()->toDateString(),
            'buyer_name' => fake()->name(),
            'buyer_address' => fake()->address(),
            'seller' => [
                'business_name' => '[DEMO] Construye Academy',
                'nif' => 'B00000000',
                'address' => 'Madrid',
                'legal_form' => 'S.L.',
                'registry' => '',
                'capital' => '',
                'phone' => '',
                'email' => '',
                'bank_details' => '',
            ],
            'lines' => [[
                'designation' => 'Curso online — acceso completo',
                'quantity' => 1,
                'unit_gross_c' => 49000,
                'unit_net_c' => 40496,
                'total_net_c' => 40496,
            ]],
            'iva_rate' => 21,
            'subtotal_net_c' => 40496,
            'discount_net_c' => 0,
            'total_net_c' => 40496,
            'iva_c' => 8504,
            'total_gross_c' => 49000,
            'currency' => 'EUR',
            'payment_method_label' => 'Efectivo',
            'payment_date' => now()->toDateString(),
        ];
    }
}
