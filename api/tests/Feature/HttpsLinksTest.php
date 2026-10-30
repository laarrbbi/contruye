<?php

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;

it('builds download links with the scheme the browser actually used', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published']);
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    Invoice::factory()->create(['order_id' => $order->id]);

    // What a TLS terminator sends: the app sees plain HTTP plus this header,
    // from an address inside the compose network (nginx sits on 172.20.0.7,
    // which is why 172.16.0.0/12 is the range that is trusted).
    $data = $this->actingAs($student)
        ->withServerVariables(['REMOTE_ADDR' => '172.20.0.7'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'construye.es'])
        ->getJson('/api/orders')
        ->assertOk()
        ->json('data.0');

    // An http:// link here is blocked by the browser as mixed content, so the
    // student simply cannot download their invoice.
    expect($data['invoice']['url'])->toStartWith('https://');
});

it('does not take the forwarded scheme from an untrusted client', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published']);
    $order = Order::factory()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => 'paid', 'paid_at' => now(),
    ]);
    Invoice::factory()->create(['order_id' => $order->id]);

    // A caller outside the trusted ranges claiming https is ignored: the
    // scheme must not be something any client can assert for itself.
    $data = $this->actingAs($student)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->getJson('/api/orders')->assertOk()->json('data.0');

    expect($data['invoice']['url'])->toStartWith('http://');
});
