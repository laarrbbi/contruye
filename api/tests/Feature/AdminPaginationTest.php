<?php

use App\Models\Course;
use App\Models\Order;
use App\Models\User;

/**
 * Controller::perPage is the single clamp behind every admin collection — it
 * replaced a copy-paste that was forgotten in one of four controllers, where
 * per_page=-1 returned a 500 and a large value dumped the whole table to
 * anyone with an admin session.
 *
 * AdminOrdersTest proves per_page=2 is honoured. These pin the edges: nothing
 * above stops an attacker (or a bookmarked URL) from asking for -1 or 100000.
 */
it('clamps per_page to a sane window on every admin collection', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    Order::factory()->count(3)->create(['course_id' => $course->id]);

    $perPage = fn (string $query) => $this->actingAs($admin)
        ->getJson("/api/admin/orders{$query}")
        ->assertOk()
        ->json('meta.per_page');

    // The default, and the one value AdminOrdersTest already covers.
    expect($perPage(''))->toBe(20)
        ->and($perPage('?per_page=2'))->toBe(2)
        // Below the floor: paginate(0) divides by zero and paginate(-1) used
        // to 500 outright. Both become one row per page.
        ->and($perPage('?per_page=0'))->toBe(1)
        ->and($perPage('?per_page=-1'))->toBe(1)
        // Exactly on the ceiling, and past it.
        ->and($perPage('?per_page=100'))->toBe(100)
        ->and($perPage('?per_page=101'))->toBe(100)
        ->and($perPage('?per_page=100000'))->toBe(100)
        // Junk casts to 0 and lands on the floor rather than on an error.
        ->and($perPage('?per_page=abc'))->toBe(1)
        ->and($perPage('?per_page=2.9'))->toBe(2)
        // An empty parameter is treated as absent, not as zero.
        ->and($perPage('?per_page='))->toBe(20);
});

it('clamps per_page on the students list, which reports no per_page of its own', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    // /admin/students hand-rolls its meta, so the clamp is only observable
    // through the page count — this is the endpoint C25/C28/C50/S11 named,
    // and the one where a raw query value would have gone unnoticed.
    $this->actingAs($admin)->getJson('/api/admin/students?per_page=-1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 3);

    $this->actingAs($admin)->getJson('/api/admin/students?per_page=100000')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.last_page', 1);
});

it('never serves more than one hundred students however many are asked for', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(101)->create();

    // The ceiling is what stops one request from streaming the whole student
    // table — names, emails and phone numbers — in a single response.
    $this->actingAs($admin)->getJson('/api/admin/students?per_page=100000')
        ->assertOk()
        ->assertJsonCount(100, 'data')
        ->assertJsonPath('meta.total', 101)
        ->assertJsonPath('meta.last_page', 2);
});

it('clamps per_page on the invoice list too', function () {
    $admin = User::factory()->admin()->create();

    // Invoices are numbered fiscal documents; the same clamp has to hold on
    // the endpoint that lists them.
    $this->actingAs($admin)->getJson('/api/admin/invoices?per_page=-1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);

    $this->actingAs($admin)->getJson('/api/admin/invoices?per_page=99999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});
