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
        'business_name' => '[DÉMO] Ebni Academy',
        'legal_form' => 'EURL',
        'capital' => '1 000 000 DA',
        'address' => '12 rue des Frères Boulemdaïs, Alger',
        'rc' => '16/00-1234567 B 26',
        'nif' => '002616123456789',
        'nis' => '002616123456780',
        'ai' => '16123456789',
        'phone' => '0550 00 00 00',
        'email' => 'contact@example.dz',
        'bank_details' => '',
    ]);
}

function paidModulesOrder(User $student): Order
{
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    $modules = Module::factory()->count(3)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);

    // 3 × 12 000 with the 10 % volume tier → 32 400, like the demo data.
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 32400,
        'discount_percent' => 10,
        'currency' => 'DZD',
        'method' => 'manual_ccp',
        'scope' => 'modules',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    foreach ($modules as $module) {
        OrderModule::query()->create([
            'order_id' => $order->id,
            'module_id' => $module->id,
            'unit_price' => 12000,
        ]);
    }

    return $order;
}

function invoicePayload(array $overrides = []): array
{
    return array_merge([
        'buyer_name' => 'Karim Benali',
        'buyer_address' => 'Cité 200 logements, Bab Ezzouar, Alger',
        'tva_rate' => 19,
    ], $overrides);
}
