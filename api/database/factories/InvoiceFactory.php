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
                'business_name' => '[DÉMO] Ebni Academy',
                'legal_form' => 'EURL',
                'capital' => '100 000 DA',
                'address' => 'Alger',
                'rc' => '00/00-0000000',
                'nif' => '000000000000000',
                'nis' => '000000000000000',
                'ai' => '00000000000',
                'phone' => '',
                'email' => '',
                'bank_details' => '',
            ],
            'lines' => [[
                'designation' => 'Formation en ligne — accès complet',
                'quantity' => 1,
                'unit_ttc_c' => 9000000,
                'unit_ht_c' => 7563025,
                'total_ht_c' => 7563025,
            ]],
            'tva_rate' => 19,
            'subtotal_ht_c' => 7563025,
            'discount_ht_c' => 0,
            'total_ht_c' => 7563025,
            'tva_c' => 1436975,
            'total_ttc_c' => 9000000,
            'currency' => 'DZD',
            'payment_method_label' => 'Espèces',
            'payment_date' => now()->toDateString(),
        ];
    }
}
