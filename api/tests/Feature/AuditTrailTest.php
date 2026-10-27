<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

function admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

it('attributes every price lever an admin can pull', function () {
    $actor = admin();
    $course = Course::factory()->create(['status' => 'draft', 'price_dzd' => 90000]);
    $module = Module::factory()->for($course)->create(['order' => 1, 'price_dzd' => 12000]);

    // Course price and publication state.
    $this->actingAs($actor)->putJson("/api/admin/courses/{$course->id}", [
        'title_fr' => $course->title_fr,
        'title_ar' => $course->title_ar,
        'price_dzd' => 120000,
        'status' => 'published',
    ])->assertOk();

    // Per-module price, which every à-la-carte quote is built from.
    $this->actingAs($actor)->putJson("/api/admin/modules/{$module->id}", [
        'title_fr' => 'Module renommé',
        'title_ar' => $module->title_ar,
        'price_dzd' => 30000,
    ])->assertOk();

    // A discount code.
    $couponId = $this->actingAs($actor)->postJson('/api/admin/coupons', [
        'code' => 'AUDIT10',
        'percent_off' => 10,
    ])->assertCreated()->json('data.id');

    $this->actingAs($actor)->putJson("/api/admin/coupons/{$couponId}", [
        'code' => 'AUDIT10',
        'percent_off' => 90,
    ])->assertOk();

    $actions = AuditLog::query()->where('user_id', $actor->id)->pluck('action')->all();

    expect($actions)->toContain('course.updated', 'module.updated', 'coupon.created', 'coupon.updated');

    // The trail has to carry the old value, or it cannot answer "who changed
    // this price, and from what".
    $courseEntry = AuditLog::query()->where('action', 'course.updated')->latest('id')->first();
    expect($courseEntry->meta['before']['price_dzd'])->toBe(90000)
        ->and($courseEntry->meta['after']['price_dzd'])->toBe(120000)
        ->and($courseEntry->meta['before']['status'])->toBe('draft');

    $couponEntry = AuditLog::query()->where('action', 'coupon.updated')->latest('id')->first();
    expect($couponEntry->meta['before']['percent_off'])->toBe(10)
        ->and($couponEntry->meta['after']['percent_off'])->toBe(90);
});

it('records deletions of catalogue content with enough to identify what was lost', function () {
    $actor = admin();
    $course = Course::factory()->create();
    $module = Module::factory()->for($course)->create(['order' => 1, 'title_fr' => 'Module supprimé']);
    $lesson = Lesson::factory()->for($module)->create(['order' => 1, 'title_fr' => 'Leçon supprimée']);

    $this->actingAs($actor)->deleteJson("/api/admin/lessons/{$lesson->id}")->assertNoContent();
    $this->actingAs($actor)->deleteJson("/api/admin/modules/{$module->id}")->assertNoContent();

    $lessonEntry = AuditLog::query()->where('action', 'lesson.deleted')->latest('id')->first();
    $moduleEntry = AuditLog::query()->where('action', 'module.deleted')->latest('id')->first();

    expect($lessonEntry)->not->toBeNull()
        ->and($lessonEntry->meta['title'])->toBe('Leçon supprimée')
        ->and($moduleEntry)->not->toBeNull()
        ->and($moduleEntry->meta['title'])->toBe('Module supprimé')
        // The record must survive the row it describes.
        ->and(Module::find($module->id))->toBeNull();
});

it('names the actor, never leaving a change anonymous', function () {
    $actor = admin();
    $coupon = Coupon::factory()->create(['code' => 'TRACE', 'percent_off' => 5]);

    $this->actingAs($actor)->deleteJson("/api/admin/coupons/{$coupon->id}")->assertNoContent();

    $entry = AuditLog::query()->where('action', 'coupon.deleted')->latest('id')->first();

    expect($entry->user_id)->toBe($actor->id)
        ->and($entry->auditable_id)->toBe($coupon->id)
        ->and($entry->meta['code'])->toBe('TRACE');
});

it('attributes promotions and the seller block every facture is printed from', function () {
    $actor = admin();
    $course = Course::factory()->create(['price_dzd' => 90000]);

    $campaignId = $this->actingAs($actor)->postJson('/api/admin/campaigns', [
        'course_id' => $course->id,
        'label_fr' => 'Rentrée 2026',
        'label_ar' => 'الدخول 2026',
        'percent_off' => 30,
        'ends_at' => now()->addWeek()->toISOString(),
    ])->assertCreated()->json('data.id');

    $this->actingAs($actor)->putJson("/api/admin/campaigns/{$campaignId}", [
        'course_id' => $course->id,
        'label_fr' => 'Rentrée 2026',
        'label_ar' => 'الدخول 2026',
        'percent_off' => 50,
        'ends_at' => now()->addWeek()->toISOString(),
    ])->assertOk();

    $this->actingAs($actor)->deleteJson("/api/admin/campaigns/{$campaignId}")->assertOk();

    // The émetteur block is a fiscal identity: an RC or NIF correction has to
    // be attributable even though it edits a settings row and not a model.
    $this->actingAs($actor)->putJson('/api/admin/invoicing/settings', [
        'business_name' => 'Ebni Academy',
        'legal_form' => 'EURL',
        'capital' => '1 000 000 DA',
        'address' => 'Alger',
        'rc' => '16/00-1234567 B 26',
        'nif' => '002616123456789',
        'nis' => '002616123456780',
        'ai' => '16123456789',
    ])->assertOk();

    $actions = AuditLog::query()->where('user_id', $actor->id)->pluck('action')->all();

    expect($actions)->toContain(
        'campaign.created',
        'campaign.updated',
        'campaign.deleted',
        'invoicing.settings_updated',
    );

    // A promotion is a price lever: the trail of its removal has to outlive
    // the row, or a 50 % discount can vanish with nothing to show for it.
    $deleted = AuditLog::query()->where('action', 'campaign.deleted')->latest('id')->first();

    expect($deleted->auditable_id)->toBe($campaignId)
        ->and($deleted->user_id)->toBe($actor->id)
        ->and(Campaign::find($campaignId))->toBeNull();
});

it('records the money path: invoices issued and corrected, campaigns changed', function () {
    $actor = admin();
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    $invoice = $this->actingAs($actor)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()->json('data');

    $this->actingAs($actor)
        ->putJson("/api/admin/invoices/{$invoice['id']}", invoicePayload(['buyer_name' => 'Nom corrigé']))
        ->assertOk();

    // A campaign moves the advertised price of the whole course.
    $campaignId = $this->actingAs($actor)->postJson('/api/admin/campaigns', [
        'course_id' => $order->course_id,
        'label_fr' => 'Rentrée', 'label_ar' => 'الدخول',
        'percent_off' => 25,
        'ends_at' => now()->addDays(10)->toIso8601String(),
        'is_active' => true,
    ])->assertCreated()->json('data.id');

    // 200 with a body, not 204 — the contract inconsistency Phase 1 recorded
    // across the admin DELETE routes. Pinned here as it actually behaves, so a
    // future clean-up is a deliberate change rather than a silent one.
    $this->actingAs($actor)->deleteJson("/api/admin/campaigns/{$campaignId}")->assertOk();

    $actions = AuditLog::query()->where('user_id', $actor->id)->pluck('action')->all();

    expect($actions)->toContain('invoice.created', 'invoice.updated', 'campaign.created', 'campaign.deleted');

    // A correction to a numbered fiscal document must keep the old values,
    // or the trail cannot show what the client originally received.
    $correction = AuditLog::query()->where('action', 'invoice.updated')->latest('id')->first();

    expect($correction->meta['number'])->toBe($invoice['number'])
        ->and($correction->meta['before']['buyer_name'])->toBe('Karim Benali')
        ->and($correction->meta['after']['buyer_name'])->toBe('Nom corrigé');
});
