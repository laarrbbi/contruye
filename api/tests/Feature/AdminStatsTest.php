<?php

use App\Mail\WelcomeMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('blocks non-admins from stats, students and export', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->getJson('/api/admin/stats')->assertForbidden();
    $this->actingAs($student)->getJson('/api/admin/students')->assertForbidden();
    $this->actingAs($student)->get('/api/admin/orders/export')->assertForbidden();
});

it('computes monthly revenue by currency and orders by method', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    Order::factory()->paid()->create(['course_id' => $course->id, 'amount' => 30000, 'currency' => 'DZD', 'method' => 'chargily']);
    Order::factory()->paid()->create(['course_id' => $course->id, 'amount' => 250, 'currency' => 'EUR', 'method' => 'stripe']);
    // Paid last month — excluded from this month's revenue. Method pinned:
    // the factory picks randomly and would skew the per-method counts.
    Order::factory()->paid()->create(['course_id' => $course->id, 'amount' => 99999, 'currency' => 'DZD', 'method' => 'manual_baridimob', 'paid_at' => now()->subMonth()]);
    Order::factory()->create(['course_id' => $course->id, 'method' => 'manual_ccp', 'status' => 'awaiting_review', 'receipt_path' => 'receipts/x.jpg']);

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    expect($response->json('data.revenue_this_month.DZD'))->toBe(30000)
        ->and($response->json('data.revenue_this_month.EUR'))->toBe(250)
        ->and($response->json('data.orders_awaiting_review'))->toBe(1)
        ->and($response->json('data.orders_by_method.chargily.paid'))->toBe(1);
});

it('computes the average completion rate across enrollments', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $lessons = Lesson::factory()->count(4)->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['module_id' => $module->id]);

    [$userA, $userB] = User::factory()->count(2)->create();
    Enrollment::factory()->create(['user_id' => $userA->id, 'course_id' => $course->id]);
    Enrollment::factory()->create(['user_id' => $userB->id, 'course_id' => $course->id]);

    // A completed 4/4, B completed 1/4 → average (100 + 25) / 2 = 62.5 → 63
    foreach ($lessons as $lesson) {
        LessonProgress::factory()->completed()->create(['user_id' => $userA->id, 'lesson_id' => $lesson->id]);
    }
    LessonProgress::factory()->completed()->create(['user_id' => $userB->id, 'lesson_id' => $lessons[0]->id]);

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    expect($response->json('data.completion_rate'))->toBe(63);
});

it('lists and searches students with enrollment details', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Yacine Recherché', 'email' => 'yacine.r@example.dz']);
    User::factory()->count(3)->create();

    $list = $this->actingAs($admin)->getJson('/api/admin/students?search=Recherché')->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.email'))->toBe('yacine.r@example.dz');

    $course = Course::factory()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lessons = Lesson::factory()->count(2)->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['module_id' => $module->id]);
    Enrollment::factory()->create(['user_id' => $target->id, 'course_id' => $course->id]);
    LessonProgress::factory()->completed()->create(['user_id' => $target->id, 'lesson_id' => $lessons[0]->id]);

    $show = $this->actingAs($admin)->getJson("/api/admin/students/{$target->id}")->assertOk();
    expect($show->json('data.enrollments.0.progress_percent'))->toBe(50)
        ->and($show->json('data.enrollments.0.total_lessons'))->toBe(2);
});

it('exports filtered orders as csv', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['title_fr' => 'Formation CSV']);
    $buyer = User::factory()->create(['name' => 'Client CSV']);

    Order::factory()->paid()->create(['user_id' => $buyer->id, 'course_id' => $course->id, 'method' => 'chargily']);
    Order::factory()->create(['course_id' => $course->id, 'method' => 'manual_ccp']);

    $response = $this->actingAs($admin)->get('/api/admin/orders/export?method=chargily');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Client CSV')
        ->toContain('Formation CSV')
        ->not->toContain('manual_ccp');
});

it('queues the welcome email on registration', function () {
    Mail::fake();

    $this->postJson('/api/register', [
        'name' => 'Nouvelle Étudiante',
        'email' => 'nouvelle@example.dz',
        'phone' => '0551112233',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'locale' => 'ar',
    ])->assertCreated();

    Mail::assertQueued(WelcomeMail::class, function ($mail) {
        return $mail->user->email === 'nouvelle@example.dz' && $mail->locale === 'ar';
    });
});
