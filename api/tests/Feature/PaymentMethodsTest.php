<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\User;
use App\Services\StripeService;

/**
 * A checkout method is offered only when it can take money. A gateway with a
 * missing key used to be offered anyway: the buyer picked it, the order was
 * created, the provider call failed, and a `failed` order was left behind for
 * a payment that could never have happened. A bank-transfer screen with no
 * IBAN on it is the same defect without the 502.
 */
it('offers every method once each one is configured', function () {
    $this->getJson('/api/checkout/methods')
        ->assertOk()
        ->assertExactJson(['data' => ['stripe', 'manual_transfer', 'manual_bizum']]);
});

it('drops a method the moment one of its credentials is blank', function (string $blankKey, string $dropped) {
    config([$blankKey => '  ']);

    $methods = $this->getJson('/api/checkout/methods')->assertOk()->json('data');

    expect($methods)->not->toContain($dropped)->and($methods)->toHaveCount(2);
})->with([
    'stripe api key' => ['services.stripe.secret', 'stripe'],
    'stripe webhook secret' => ['services.stripe.webhook_secret', 'stripe'],
    'transfer IBAN' => ['services.manual_payment.iban', 'manual_transfer'],
    'transfer account holder' => ['services.manual_payment.holder', 'manual_transfer'],
    'bizum phone' => ['services.manual_payment.bizum_phone', 'manual_bizum'],
]);

it('withdraws a gateway the owner switched off, even with its keys set', function () {
    config(['services.stripe.enabled' => false]);

    $this->getJson('/api/checkout/methods')
        ->assertOk()
        ->assertExactJson(['data' => ['manual_transfer', 'manual_bizum']]);
});

it('refuses an unavailable method before any order exists', function () {
    // A gateway that would fail the test if it were ever reached.
    app()->instance(StripeService::class, new class extends StripeService
    {
        public function __construct() {}

        public function createCheckoutSession(...$args): array
        {
            throw new LogicException('The provider must not be called.');
        }
    });
    config(['services.stripe.webhook_secret' => '']);

    $course = Course::factory()->create(['price_eur' => 490]);
    $coupon = Coupon::factory()->create(['code' => 'LANZAMIENTO', 'percent_off' => 10, 'max_uses' => 5, 'uses' => 0]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'stripe',
            'coupon_code' => 'LANZAMIENTO',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

    // No order, no provider call, and the capped coupon keeps its use.
    $this->assertDatabaseCount('orders', 0);
    expect($coupon->fresh()->uses)->toBe(0);
});

it('refuses a bank-transfer order while no IBAN is configured', function () {
    config(['services.manual_payment.iban' => null]);

    $course = Course::factory()->create(['price_eur' => 490]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_transfer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

    $this->assertDatabaseCount('orders', 0);
});

it('explains the refusal in the buyer\'s language', function () {
    config(['services.stripe.enabled' => false]);

    $course = Course::factory()->create(['price_eur' => 300]);

    $this->actingAs(User::factory()->create())
        ->withHeader('Accept-Language', 'es')
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.method.0', 'Este método de pago no está disponible en este momento.');
});
