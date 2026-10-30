<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\Order;
use App\Models\OrderModule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared fixtures
|--------------------------------------------------------------------------
|
| Helpers used by more than one test file live here, not in whichever file
| happened to need them first. Under `--parallel` each worker loads only its
| own files, so a helper declared in a sibling test simply does not exist —
| which is how this was found.
|
*/

function completeSellerSettings(): void
{
    Setting::setValue('invoicing.seller', [
        'business_name' => '[DEMO] Construye Academy',
        'nif' => 'B12345674',
        'address' => 'Calle Mayor 1, 28013 Madrid',
        'legal_form' => 'S.L.',
        'registry' => 'Inscrita en el Registro Mercantil de Madrid, tomo 1, folio 1, hoja M-1',
        'capital' => '3.000 €',
        'phone' => '600 00 00 00',
        'email' => 'contact@example.es',
        'bank_details' => '',
    ]);
}

function paidModulesOrder(User $student): Order
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 490]);
    $modules = Module::factory()->count(3)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_eur' => 49]);

    // 3 × 49 with the 10 % volume tier → 132, like the demo data.
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 132,
        'discount_percent' => 10,
        'currency' => 'EUR',
        'method' => 'manual_transfer',
        'scope' => 'modules',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    foreach ($modules as $module) {
        OrderModule::query()->create([
            'order_id' => $order->id,
            'module_id' => $module->id,
            'unit_price' => 49,
        ]);
    }

    return $order;
}

function invoicePayload(array $overrides = []): array
{
    return array_merge([
        'buyer_name' => 'Javier Morales',
        'buyer_address' => 'Avenida de la Constitución 12, 41001 Sevilla',
        'iva_rate' => 21,
    ], $overrides);
}
