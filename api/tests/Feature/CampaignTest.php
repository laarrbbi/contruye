<?php

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Support\Facades\Http;

function campaignCourse(): Course
{
    $course = Course::factory()->create([
        'status' => 'published',
        'price_dzd' => 90000,
        'price_eur' => 300,
        'module_discount_tiers' => null,
    ]);

    Module::factory()->count(10)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000, 'price_eur' => 40]);

    return $course->fresh();
}

it('applies a running campaign to the full-access price', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 20]);

    $quote = app(PricingService::class)->quote($course, null, 'full', [], 'DZD');

    expect($quote['total'])->toBe(72000) // 90 000 − 20%
        ->and($quote['campaign_percent'])->toBe(20)
        ->and($quote['campaign_discount'])->toBe(18000)
        ->and($quote['campaign']['percent_off'])->toBe(20);
});

it('applies the campaign after the volume tier on a module selection', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 10]);
    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();

    // 3 modules: 36 000 → tier 10% = 32 400 → campaign 10% = 29 160.
    $quote = app(PricingService::class)->quote($course, $user, 'modules', array_slice($ids, 0, 3), 'DZD');

    expect($quote['tier_percent'])->toBe(10)
        ->and($quote['campaign_percent'])->toBe(10)
        ->and($quote['campaign_discount'])->toBe(3240)
        ->and($quote['total'])->toBe(29160);
});

it('raises the price again the moment the campaign deadline passes', function () {
    $course = campaignCourse();
    $campaign = Campaign::factory()->create([
        'course_id' => $course->id,
        'percent_off' => 20,
        'ends_at' => now()->addHour(),
    ]);
    $pricing = app(PricingService::class);

    expect($pricing->quote($course->fresh(), null, 'full', [], 'DZD')['total'])->toBe(72000);

    // Travel past the real deadline — no data is changed, time simply moves on.
    $this->travel(2)->hours();

    $after = $pricing->quote($course->fresh(), null, 'full', [], 'DZD');
    expect($after['total'])->toBe(90000)          // genuinely back to full price
        ->and($after['campaign'])->toBeNull()
        ->and($after['campaign_percent'])->toBe(0);

    // And the deadline itself never moved.
    expect($campaign->fresh()->ends_at->timestamp)->toBe($campaign->ends_at->timestamp);
});

it('ignores campaigns that are disabled, expired or not started yet', function () {
    $pricing = app(PricingService::class);

    $disabled = campaignCourse();
    Campaign::factory()->disabled()->create(['course_id' => $disabled->id, 'percent_off' => 50]);
    expect($pricing->quote($disabled->fresh(), null, 'full', [], 'DZD')['total'])->toBe(90000);

    $expired = campaignCourse();
    Campaign::factory()->expired()->create(['course_id' => $expired->id, 'percent_off' => 50]);
    expect($pricing->quote($expired->fresh(), null, 'full', [], 'DZD')['total'])->toBe(90000);

    $upcoming = campaignCourse();
    Campaign::factory()->upcoming()->create(['course_id' => $upcoming->id, 'percent_off' => 50]);
    expect($pricing->quote($upcoming->fresh(), null, 'full', [], 'DZD')['total'])->toBe(90000);
});

it('picks the best running campaign when several overlap', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 10]);
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 30]);

    $quote = app(PricingService::class)->quote($course->fresh(), null, 'full', [], 'DZD');

    expect($quote['campaign_percent'])->toBe(30)
        ->and($quote['total'])->toBe(63000);
});

it('stacks a coupon on top of the campaign without going negative', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 50]);
    $coupon = Coupon::factory()->create(['percent_off' => 50]);

    $quote = app(PricingService::class)->quote($course->fresh(), null, 'full', [], 'DZD', $coupon);

    // 90 000 → 45 000 (campaign) → 22 500 (coupon)
    expect($quote['campaign_discount'])->toBe(45000)
        ->and($quote['coupon_discount'])->toBe(22500)
        ->and($quote['total'])->toBe(22500)
        ->and($quote['total'])->toBeGreaterThanOrEqual(0);
});

it('charges the campaign price at checkout, matching the quote exactly', function () {
    Http::fake(['*' => Http::response(['id' => '01chk', 'checkout_url' => 'https://pay.test/x'], 200)]);

    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 25]);
    $user = User::factory()->create();

    $quoted = $this->actingAs($user)->postJson('/api/checkout/quote', [
        'course_id' => $course->id,
        'scope' => 'full',
    ])->assertOk()->json('data.total');

    $charged = $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'scope' => 'full',
        'method' => 'chargily',
    ])->assertCreated()->json('data.order.amount');

    expect($quoted)->toBe(67500)
        ->and($charged)->toBe($quoted);

    expect(Order::latest('id')->first()->amount)->toBe(67500);
});

it('exposes the real campaign deadline on the public course payload', function () {
    $course = campaignCourse();
    $campaign = Campaign::factory()->create([
        'course_id' => $course->id,
        'percent_off' => 15,
        'label_es' => 'Offre de lancement',
        'ends_at' => now()->addDays(2),
    ]);

    $response = $this->getJson("/api/courses/{$course->slug}")->assertOk();

    expect($response->json('data.campaign.percent_off'))->toBe(15)
        ->and($response->json('data.campaign.label'))->toBe('Offre de lancement')
        ->and($response->json('data.campaign.ends_at'))->toBe($campaign->ends_at->toISOString());

    // No promotion → no countdown to show.
    $plain = campaignCourse();
    $this->getJson("/api/courses/{$plain->slug}")
        ->assertOk()
        ->assertJsonPath('data.campaign', null);
});

it('keeps a campaign deadline identical across visitors and requests', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 15]);

    $first = $this->getJson("/api/courses/{$course->slug}")->json('data.campaign.ends_at');

    // A different visitor, later request — the deadline must not restart.
    $this->travel(30)->minutes();
    $second = $this->actingAs(User::factory()->create())
        ->getJson("/api/courses/{$course->slug}")->json('data.campaign.ends_at');

    expect($second)->toBe($first);
});

it('lets an admin manage campaigns and refuses dishonest deadlines', function () {
    $course = campaignCourse();
    $admin = User::factory()->create(['role' => 'admin']);

    // Create
    $id = $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'Offre de rentrée',
        'label_en' => 'عرض الدخول',
        'percent_off' => 20,
        'ends_at' => now()->addDays(5)->toISOString(),
    ])->assertCreated()->assertJsonPath('data.is_running', true)->json('data.id');

    // A deadline in the past is refused — a countdown must be real.
    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'x', 'label_en' => 'x',
        'percent_off' => 20,
        'ends_at' => now()->subDay()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);

    // No end date at all is refused too.
    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'x', 'label_en' => 'x',
        'percent_off' => 20,
    ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);

    // 100% off is out of range.
    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'x', 'label_en' => 'x',
        'percent_off' => 100,
        'ends_at' => now()->addDay()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['percent_off']);

    // Update + delete
    $this->actingAs($admin)->putJson("/api/admin/campaigns/{$id}", [
        'course_id' => $course->id,
        'label_es' => 'Offre de rentrée', 'label_en' => 'عرض الدخول',
        'percent_off' => 25,
        'ends_at' => now()->addDays(5)->toISOString(),
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.percent_off', 25)->assertJsonPath('data.is_running', false);

    $this->actingAs($admin)->deleteJson("/api/admin/campaigns/{$id}")->assertOk();
    $this->assertDatabaseCount('campaigns', 0);
});

it('forbids campaign management for non-admins', function () {
    $course = campaignCourse();
    $student = User::factory()->create();

    $this->actingAs($student)->getJson('/api/admin/campaigns')->assertForbidden();
    $this->actingAs($student)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'x', 'label_en' => 'x',
        'percent_off' => 50,
        'ends_at' => now()->addDay()->toISOString(),
    ])->assertForbidden();
});

it('bases the upgrade hint on the discounted full price during a campaign', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 20]);
    $ids = $course->modules()->orderBy('order')->pluck('id')->all();
    $user = User::factory()->create();

    // 9 modules: 108 000 → tier 20% = 86 400 → campaign 20% = 69 120.
    // Full access during the campaign costs 90 000 − 20% = 72 000.
    $quote = app(PricingService::class)->quote($course->fresh(), $user, 'modules', array_slice($ids, 0, 9), 'DZD');

    expect($quote['total'])->toBe(69120)
        // The top-up is measured against what full access really costs now.
        ->and($quote['upgrade_hint']['price_delta'])->toBe(2880)
        ->and($quote['upgrade_hint']['modules_missing'])->toBe(1);
});

it('keeps the campaign deadline stable when two campaigns share the same discount', function () {
    $course = campaignCourse();
    Campaign::factory()->create([
        'course_id' => $course->id, 'percent_off' => 20, 'ends_at' => now()->addDays(5),
    ]);
    $soonest = Campaign::factory()->create([
        'course_id' => $course->id, 'percent_off' => 20, 'ends_at' => now()->addDays(2),
    ]);

    // Deterministic tie-break: soonest deadline wins, identically every call.
    $first = app(PricingService::class)->quote($course->fresh(), null, 'full', [], 'DZD')['campaign'];
    $second = app(PricingService::class)->quote($course->fresh(), null, 'full', [], 'DZD')['campaign'];

    expect($first['ends_at'])->toBe($soonest->ends_at->toISOString())
        ->and($second['ends_at'])->toBe($first['ends_at']);
});

it('rejects a campaign window that could never run', function () {
    $course = campaignCourse();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'Inversée', 'label_en' => 'مقلوب',
        'percent_off' => 20,
        'starts_at' => now()->addDays(9)->toISOString(),
        'ends_at' => now()->addDays(3)->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('lets an admin edit a finished campaign without resurrecting the discount', function () {
    $course = campaignCourse();
    $admin = User::factory()->create(['role' => 'admin']);
    $ended = Campaign::factory()->expired()->create(['course_id' => $course->id, 'percent_off' => 20]);

    // Renaming / disabling a past promotion must not require a future end date.
    $this->actingAs($admin)->putJson("/api/admin/campaigns/{$ended->id}", [
        'course_id' => $course->id,
        'label_es' => 'Offre de printemps (terminée)',
        'label_en' => 'عرض الربيع (منتهٍ)',
        'percent_off' => 20,
        'ends_at' => $ended->ends_at->toISOString(),
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_running', false);

    expect($ended->fresh()->label_es)->toBe('Offre de printemps (terminée)');

    // Creating a new campaign still demands a real, future deadline.
    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'x', 'label_en' => 'x',
        'percent_off' => 20,
        'ends_at' => now()->subDay()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
});

it('shows the campaign price on the public catalog too', function () {
    $course = campaignCourse();
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 20]);

    $card = collect($this->getJson('/api/courses')->assertOk()->json('data'))
        ->firstWhere('id', $course->id);

    expect($card['campaign']['percent_off'])->toBe(20);
});

it('starts a promotion with no Arabic name; Arabic visitors read the French one', function () {
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_es' => 'Offre de lancement',
        'percent_off' => 20,
        'ends_at' => now()->addDays(3)->toISOString(),
    ])->assertCreated()->assertJsonPath('data.label_en', null);

    $this->withHeader('Accept-Language', 'ar')
        ->getJson("/api/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.campaign.label', 'Offre de lancement');
});
