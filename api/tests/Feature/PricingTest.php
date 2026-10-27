<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Build the reference course: 10 modules at 12 000 DZD / 40 EUR,
 * full access 90 000 DZD / 300 EUR, default tiers.
 */
function pricedCourse(): Course
{
    $course = Course::factory()->create([
        'price_dzd' => 90000,
        'price_eur' => 300,
        'module_discount_tiers' => null, // defaults apply
    ]);

    Module::factory()->count(10)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);

    return $course->fresh();
}

it('prices module selections exactly per the tier table', function (int $count, int $expectedTotal, int $expectedPercent) {
    $course = pricedCourse();
    $user = User::factory()->create();
    $moduleIds = $course->modules()->limit($count)->pluck('id')->all();

    $quote = app(PricingService::class)->quote($course, $user, 'modules', $moduleIds, 'DZD');

    expect($quote['total'])->toBe($expectedTotal)
        ->and($quote['tier_percent'])->toBe($expectedPercent)
        ->and($quote['scope'])->toBe($count === 10 ? 'full' : 'modules');
})->with([
    '1 module — 0%' => [1, 12000, 0],
    '2 modules — 5%' => [2, 22800, 5],
    '3 modules — 10%' => [3, 32400, 10],
    '4 modules — 10%' => [4, 43200, 10],
    '5 modules — 15%' => [5, 51000, 15],
    '6 modules — 15%' => [6, 61200, 15],
    '7 modules — 20%' => [7, 67200, 20],
    '9 modules — 20%' => [9, 86400, 20],
    '10 modules — auto-upgrade to full' => [10, 90000, 25],
]);

it('holds the pricing invariants: monotonic totals and no split beats full access', function () {
    $course = pricedCourse();
    $user = User::factory()->create();
    $ids = $course->modules()->pluck('id')->all();
    $pricing = app(PricingService::class);

    $totals = [];
    foreach (range(1, 10) as $n) {
        $totals[$n] = $pricing->quote($course, $user, 'modules', array_slice($ids, 0, $n), 'DZD')['total'];
    }

    // Strictly increasing with the module count.
    foreach (range(2, 10) as $n) {
        expect($totals[$n])->toBeGreaterThan($totals[$n - 1]);
    }

    // Any two-order split costs at least the full-access price.
    foreach ([[9, 1], [7, 3], [5, 5], [6, 4]] as [$a, $b]) {
        expect($totals[$a] + $totals[$b])->toBeGreaterThanOrEqual(90000);
    }

    // Buying one by one costs the full anchor.
    expect(10 * $totals[1])->toBe(120000)
        ->and($totals[10])->toBe(90000);
});

it('quotes EUR totals for stripe with exact integers', function () {
    $course = pricedCourse();
    $user = User::factory()->create();
    $ids = $course->modules()->pluck('id')->all();
    $pricing = app(PricingService::class);

    expect($pricing->quote($course, $user, 'modules', array_slice($ids, 0, 2), 'EUR')['total'])->toBe(76)
        ->and($pricing->quote($course, $user, 'modules', array_slice($ids, 0, 5), 'EUR')['total'])->toBe(170)
        ->and($pricing->quote($course, $user, 'full', [], 'EUR')['total'])->toBe(300);
});

it('serves live quotes to guests and excludes owned modules for buyers', function () {
    $course = pricedCourse();
    $ids = $course->modules()->orderBy('order')->pluck('id')->all();

    // Guest quote — includes savings comparison for the UI.
    $guest = $this->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => array_slice($ids, 0, 3),
    ])->assertOk();

    expect($guest->json('data.total'))->toBe(32400)
        ->and($guest->json('data.tier_percent'))->toBe(10)
        ->and($guest->json('data.full_price_anchor'))->toBe(120000)
        ->and($guest->json('data.full_price'))->toBe(90000);

    // A user who owns modules 1-2 asking for 1-2-3 is quoted for 3 only.
    $owner = User::factory()->create();
    foreach (array_slice($ids, 0, 2) as $ownedId) {
        Enrollment::factory()->create([
            'user_id' => $owner->id,
            'course_id' => $course->id,
            'module_id' => $ownedId,
        ]);
    }

    $refined = $this->actingAs($owner)->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => array_slice($ids, 0, 3),
    ])->assertOk();

    expect($refined->json('data.total'))->toBe(12000)
        ->and($refined->json('data.owned_module_ids'))->toHaveCount(2);
});

it('rejects quotes for foreign modules and fully-owned selections', function () {
    $course = pricedCourse();
    $other = Module::factory()->create(['price_dzd' => 12000]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => [$other->id],
    ])->assertUnprocessable();

    Enrollment::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'module_id' => null, // full access
    ]);

    $this->actingAs($user)->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => [$course->modules()->first()->id],
    ])->assertUnprocessable();
});

it('checks out modules à la carte and enrolls per module on payment', function () {
    Http::fake(['*' => Http::response(['id' => '01chk', 'checkout_url' => 'https://pay.test/x'], 200)]);

    $course = pricedCourse();
    $user = User::factory()->create();
    $picked = $course->modules()->orderBy('order')->limit(3)->pluck('id')->all();

    $response = $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => $picked,
        'method' => 'chargily',
    ])->assertCreated();

    $orderId = $response->json('data.order.id');
    expect($response->json('data.order.amount'))->toBe(32400)
        ->and($response->json('data.order.scope'))->toBe('modules')
        ->and($response->json('data.order.discount_percent'))->toBe(10)
        ->and($response->json('data.order.modules'))->toHaveCount(3);

    $order = Order::find($orderId);
    expect($order->orderModules()->count())->toBe(3);

    $order->markPaidAndEnroll();

    // One enrollment per module, none for the course as a whole.
    expect(Enrollment::where('user_id', $user->id)->whereNotNull('module_id')->count())->toBe(3)
        ->and(Enrollment::where('user_id', $user->id)->whereNull('module_id')->exists())->toBeFalse()
        ->and($user->fresh()->hasFullAccessTo($course))->toBeFalse()
        ->and($user->fresh()->hasAccessToModule($course->modules()->orderBy('order')->first()))->toBeTrue();

    // Idempotent under double delivery.
    $order->refresh()->markPaidAndEnroll();
    expect(Enrollment::where('user_id', $user->id)->count())->toBe(3);
});

it('auto-upgrades a full module selection to full access pricing', function () {
    Http::fake(['*' => Http::response(['id' => '01chk', 'checkout_url' => 'https://pay.test/x'], 200)]);

    $course = pricedCourse();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => $course->modules()->pluck('id')->all(),
        'method' => 'chargily',
    ])->assertCreated();

    expect($response->json('data.order.amount'))->toBe(90000)
        ->and($response->json('data.order.scope'))->toBe('full');

    Order::find($response->json('data.order.id'))->markPaidAndEnroll();
    expect($user->fresh()->hasFullAccessTo($course))->toBeTrue();
});

it('charges a partial owner the itemized price for their remaining modules, not full access', function () {
    $course = pricedCourse();
    $ids = $course->modules()->orderBy('order')->pluck('id')->all();

    // Owner of modules 1-5 completing the course by buying 6-10.
    $owner = User::factory()->create();
    foreach (array_slice($ids, 0, 5) as $ownedId) {
        Enrollment::factory()->create([
            'user_id' => $owner->id,
            'course_id' => $course->id,
            'module_id' => $ownedId,
        ]);
    }

    $quote = app(PricingService::class)->quote($course, $owner, 'modules', array_slice($ids, 5, 5), 'DZD');

    // 5 × 12 000 = 60 000, 15% tier → 51 000 — NOT the 90 000 full-access price.
    expect($quote['total'])->toBe(51000)
        ->and($quote['scope'])->toBe('modules');

    // Owner of nine modules buying the single remaining one pays à la carte (12 000).
    $owner2 = User::factory()->create();
    foreach (array_slice($ids, 0, 9) as $ownedId) {
        Enrollment::factory()->create([
            'user_id' => $owner2->id,
            'course_id' => $course->id,
            'module_id' => $ownedId,
        ]);
    }

    $last = app(PricingService::class)->quote($course, $owner2, 'modules', [$ids[9]], 'DZD');
    expect($last['total'])->toBe(12000)
        ->and($last['scope'])->toBe('modules');
});

it('never charges more than full access, upgrading a costly partial selection to full', function () {
    // Full access deliberately cheaper than the top-tier module bundle.
    $course = Course::factory()->create([
        'price_dzd' => 80000,
        'price_eur' => 300,
        'module_discount_tiers' => null,
    ]);
    Module::factory()->count(10)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);
    $course = $course->fresh();

    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();

    // 9 modules itemized = 108 000 × 0.8 (20% tier) = 86 400 > 80 000 full access.
    $quote = app(PricingService::class)->quote($course, $user, 'modules', array_slice($ids, 0, 9), 'DZD');

    expect($quote['total'])->toBe(80000)
        ->and($quote['scope'])->toBe('full')
        ->and($quote['upgraded_to_full'])->toBeTrue();
});

it('applies the best discount when tiers are non-monotonic', function () {
    $course = Course::factory()->create([
        'price_dzd' => 90000,
        'price_eur' => 300,
        'module_discount_tiers' => [
            ['min_modules' => 2, 'percent_off' => 20],
            ['min_modules' => 5, 'percent_off' => 10],
        ],
    ]);
    Module::factory()->count(10)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);
    $course = $course->fresh();

    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();

    // 6 modules qualifies for both tiers; the buyer gets the best (20%), not 10%.
    $quote = app(PricingService::class)->quote($course, $user, 'modules', array_slice($ids, 0, 6), 'DZD');
    expect($quote['tier_percent'])->toBe(20)
        ->and($quote['total'])->toBe(57600);
});

it('never reports a negative full-access discount when the anchor is below full price', function () {
    // Sum of module prices (100 000) is below the full-access price (120 000).
    $course = Course::factory()->create([
        'price_dzd' => 120000,
        'price_eur' => 300,
        'module_discount_tiers' => null,
    ]);
    Module::factory()->count(10)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 10000, 'price_eur' => 40]);
    $course = $course->fresh();

    $quote = app(PricingService::class)->quote($course, null, 'full', [], 'DZD');
    expect($quote['tier_percent'])->toBe(0) // clamped, never negative
        ->and($quote['total'])->toBe(120000);
});

it('upgrades an all-modules EUR selection to full access when a module has no EUR price', function () {
    $course = Course::factory()->create([
        'price_dzd' => 90000,
        'price_eur' => 300,
        'module_discount_tiers' => null,
    ]);
    Module::factory()->count(9)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);
    // Module 10 is full-access-only in EUR (no EUR price).
    Module::factory()->create([
        'course_id' => $course->id, 'order' => 10, 'price_dzd' => 12000, 'price_eur' => null,
    ]);
    $course = $course->fresh();

    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();

    $quote = app(PricingService::class)->quote($course, $user, 'modules', $ids, 'EUR');
    expect($quote['total'])->toBe(300)
        ->and($quote['scope'])->toBe('full')
        ->and($quote['upgraded_to_full'])->toBeTrue();
});

it('locks non-owned modules in the player and blocks their playback', function () {
    config([
        'services.bunny.library_id' => '123456',
        'services.bunny.token_key' => 'test-token-key',
        'services.bunny.embed_ttl' => 14400,
    ]);

    $course = pricedCourse();
    [$moduleA, $moduleB] = $course->modules()->orderBy('order')->limit(2)->get();
    $lessonA = Lesson::factory()->create(['module_id' => $moduleA->id, 'order' => 1]);
    $lessonB = Lesson::factory()->create(['module_id' => $moduleB->id, 'order' => 1]);

    $user = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'module_id' => $moduleA->id,
    ]);

    $learn = $this->actingAs($user)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    $modules = collect($learn->json('data.modules'));
    expect($modules->firstWhere('id', $moduleA->id)['is_locked'])->toBeFalse()
        ->and($modules->firstWhere('id', $moduleB->id)['is_locked'])->toBeTrue()
        ->and($learn->json('data.course.full_access'))->toBeFalse()
        ->and($learn->json('data.course.owned_modules_count'))->toBe(1)
        ->and($learn->json('data.next_lesson_id'))->toBe($lessonA->id);

    $this->actingAs($user)->getJson("/api/lessons/{$lessonA->id}/playback")->assertOk();
    $this->actingAs($user)->getJson("/api/lessons/{$lessonB->id}/playback")->assertForbidden();

    $this->actingAs($user)->postJson("/api/lessons/{$lessonA->id}/progress", ['position_seconds' => 10])->assertOk();
    $this->actingAs($user)->postJson("/api/lessons/{$lessonB->id}/progress", ['position_seconds' => 10])->assertForbidden();
});

it('summarizes partial access on the dashboard', function () {
    $course = pricedCourse();
    $modules = $course->modules()->orderBy('order')->limit(2)->get();
    foreach ($modules as $module) {
        Lesson::factory()->count(2)->sequence(fn ($seq) => ['order' => $seq->index + 1])
            ->create(['module_id' => $module->id]);
    }

    $user = User::factory()->create();
    foreach ($modules as $module) {
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
        ]);
    }

    // Complete both lessons of the first owned module → 2/4 = 50%.
    foreach ($modules->first()->lessons as $lesson) {
        LessonProgress::factory()->completed()->create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    $response = $this->actingAs($user)->getJson('/api/enrollments')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.full_access'))->toBeFalse()
        ->and($response->json('data.0.owned_modules_count'))->toBe(2)
        ->and($response->json('data.0.total_modules_count'))->toBe(10)
        ->and($response->json('data.0.course.progress_percent'))->toBe(50);
});

it('refuses a partial selection that includes a full-access-only module', function () {
    $course = Course::factory()->create([
        'price_dzd' => 90000,
        'price_eur' => 300,
        'module_discount_tiers' => null,
    ]);
    Module::factory()->count(9)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);
    // Module 10 has no EUR price: in EUR it exists only inside full access.
    $fullOnly = Module::factory()->create([
        'course_id' => $course->id, 'order' => 10, 'price_dzd' => 12000, 'price_eur' => null,
    ]);
    $course = $course->fresh();

    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();
    $selection = [$ids[0], $fullOnly->id];

    // Two of ten does not complete the course, so it cannot be itemized: the
    // module with no EUR price would be billed at (int) null = 0.
    expect(fn () => app(PricingService::class)->quote($course, $user, 'modules', $selection, 'EUR'))
        ->toThrow(ValidationException::class);

    $this->actingAs($user)->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => $selection,
        'method' => 'stripe',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('module_ids')
        ->assertJsonPath('errors.module_ids.0', 'Some modules are only available with full access.');

    // Checkout answers the same way, and bills nothing at all.
    $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'scope' => 'modules',
        'module_ids' => $selection,
        'method' => 'stripe',
    ])->assertUnprocessable()->assertJsonValidationErrors('module_ids');

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(0);

    // The refusal is per-currency, not a permanent property of the module: in
    // DZD the same two modules are sellable at the 5 % two-module tier.
    expect(app(PricingService::class)->quote($course, $user, 'modules', $selection, 'DZD')['total'])
        ->toBe(22800);
});
