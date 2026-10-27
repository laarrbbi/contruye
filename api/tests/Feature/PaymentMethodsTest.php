<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * A checkout method is offered only when it can take money. A gateway with a
 * missing key used to be offered anyway: the buyer picked it, the order was
 * created, the provider call failed, and a `failed` order was left behind for
 * a payment that could never have happened. A CCP transfer screen with no
 * account number on it is the same defect without the 502.
 */
it('offers every method once each one is configured', function () {
    $this->getJson('/api/checkout/methods')
        ->assertOk()
        ->assertExactJson(['data' => ['chargily', 'stripe', 'manual_ccp', 'manual_baridimob']]);
});

it('drops a method the moment one of its credentials is blank', function (string $blankKey, string $dropped) {
    config([$blankKey => '  ']);

    $methods = $this->getJson('/api/checkout/methods')->assertOk()->json('data');

    expect($methods)->not->toContain($dropped)->and($methods)->toHaveCount(3);
})->with([
    'chargily api key' => ['services.chargily.api_key', 'chargily'],
    'chargily secret' => ['services.chargily.secret', 'chargily'],
    'stripe api key' => ['services.stripe.secret', 'stripe'],
    'stripe webhook secret' => ['services.stripe.webhook_secret', 'stripe'],
    'ccp account number' => ['services.manual_payment.ccp_number', 'manual_ccp'],
    'ccp account holder' => ['services.manual_payment.ccp_name', 'manual_ccp'],
    'baridimob rip' => ['services.manual_payment.baridimob_rip', 'manual_baridimob'],
]);

it('withdraws a gateway the owner switched off, even with its keys set', function () {
    config(['services.chargily.enabled' => false, 'services.stripe.enabled' => false]);

    $this->getJson('/api/checkout/methods')
        ->assertOk()
        ->assertExactJson(['data' => ['manual_ccp', 'manual_baridimob']]);
});

it('refuses an unavailable method before any order exists', function () {
    Http::fake();
    config(['services.chargily.secret' => '']);

    $course = Course::factory()->create(['price_dzd' => 50000]);
    $coupon = Coupon::factory()->create(['code' => 'LANCEMENT', 'percent_off' => 10, 'max_uses' => 5, 'uses' => 0]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'chargily',
            'coupon_code' => 'LANCEMENT',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

    // No order, no provider call, and the capped coupon keeps its use.
    $this->assertDatabaseCount('orders', 0);
    Http::assertNothingSent();
    expect($coupon->fresh()->uses)->toBe(0);
});

it('refuses a CCP order while no account number is configured', function () {
    config(['services.manual_payment.ccp_number' => null]);

    $course = Course::factory()->create(['price_dzd' => 50000]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_ccp'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

    $this->assertDatabaseCount('orders', 0);
});

it('explains the refusal in the buyer\'s language', function () {
    config(['services.stripe.enabled' => false]);

    $course = Course::factory()->create(['price_eur' => 300]);

    $this->actingAs(User::factory()->create())
        ->withHeader('Accept-Language', 'fr')
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.method.0', "Ce moyen de paiement n'est pas disponible pour le moment.");
});
