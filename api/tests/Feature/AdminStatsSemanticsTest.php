<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The dashboard aggregates the two numbers the owner actually reads: this
 * month's revenue and the average completion rate. Both were rewritten (C8 for
 * the month boundary, the SQL rewrite for completion) and both are reported
 * against the business calendar, not UTC. AdminStatsTest pins the happy middle
 * of the month and a course that has lessons; these pin the edges where the
 * two implementations disagree.
 */
it('CHARACTERIZATION: reports nothing for the first Algiers hour of a new month', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    // 23:30 UTC on 31 August is already 00:30 on 1 September in Algiers: the
    // business month has turned over, the UTC one has not. This is the only
    // window in which the two calendars disagree, so it is the only window in
    // which the C8 fix is observable at all.
    $this->travelTo(CarbonImmutable::parse('2026-08-31 23:30:00', 'UTC'));

    $justSold = Order::factory()->paid()->create([
        'course_id' => $course->id,
        'amount' => 40000,
        'currency' => 'DZD',
        'method' => 'chargily',
        'paid_at' => CarbonImmutable::parse('2026-08-31 23:15:00', 'UTC'),
    ]);

    // Mid-August: last month's revenue on either calendar.
    Order::factory()->paid()->create([
        'course_id' => $course->id,
        'amount' => 70000,
        'currency' => 'DZD',
        'method' => 'chargily',
        'paid_at' => CarbonImmutable::parse('2026-08-15 10:00:00', 'UTC'),
    ]);

    Enrollment::factory()->for($course)->for(User::factory())
        ->create(['created_at' => CarbonImmutable::parse('2026-08-31 23:15:00', 'UTC')]);
    Enrollment::factory()->for($course)->for(User::factory())
        ->create(['created_at' => CarbonImmutable::parse('2026-08-15 10:00:00', 'UTC')]);

    // The sale really is 00:15 on 1 September in Algiers.
    expect($justSold->fresh()->paid_at->setTimezone('Africa/Algiers')->format('Y-m-d H:i'))
        ->toBe('2026-09-01 00:15');

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    // Recorded, not endorsed. The intended cutoff is the Algiers month start,
    // 23:00 UTC on the 31st, which would report 40 000. What ships binds the
    // Carbon by its LOCAL wall clock — the literal string '2026-09-01
    // 00:00:00' — against paid_at columns stored in UTC, so the cutoff lands
    // an hour late and the first Algiers hour of the month is reported by
    // neither month. Reverting to a plain now()->startOfMonth() answers
    // 110 000 here instead, so this number pins the line in both directions;
    // finishing the fix should turn it into 40 000, deliberately.
    expect($response->json('data.revenue_this_month.DZD'))->toBe(0)
        ->and($response->json('data.enrollments_this_month'))->toBe(0)
        ->and($response->json('data.enrollments_total'))->toBe(2);
});

it('CHARACTERIZATION: leaves enrolments on a lesson-less course out of the completion average', function () {
    $admin = User::factory()->admin()->create();

    $filmed = Course::factory()->create();
    $module = Module::factory()->for($filmed)->create(['order' => 1]);
    $lessons = Lesson::factory()->count(4)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['module_id' => $module->id]);

    // A course whose module exists but has not been filmed yet.
    $empty = Course::factory()->create();
    Module::factory()->for($empty)->create(['order' => 1]);

    $finisher = User::factory()->create();
    Enrollment::factory()->for($finisher)->for($filmed)->create();
    foreach ($lessons as $lesson) {
        LessonProgress::factory()->completed()->create([
            'user_id' => $finisher->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    Enrollment::factory()->for(User::factory())->for($empty)->create();

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    // Recorded, not endorsed: the SQL rewrite inner-joins the per-course
    // lesson totals and filters lessons_total > 0, so the lesson-less
    // enrolment leaves the DENOMINATOR too. The PHP loop it replaced kept it
    // as a 0 % sample and answered 50 for exactly this input — the Phase 3
    // claim of "preserving the previous semantics exactly" is wrong here.
    // Whoever restores the old semantics must change this number on purpose.
    expect($response->json('data.completion_rate'))->toBe(100);
});

it('counts one sample per active enrolment and ignores lapsed ones', function () {
    $admin = User::factory()->admin()->create();

    $course = Course::factory()->create();
    $moduleA = Module::factory()->for($course)->create(['order' => 1]);
    $moduleB = Module::factory()->for($course)->create(['order' => 2]);
    $lessonA = Lesson::factory()->for($moduleA)->create(['order' => 1]);
    Lesson::factory()->for($moduleB)->create(['order' => 1]);

    // A student who bought two modules separately holds two enrollment rows
    // and therefore weighs twice — 50 % counted twice is still 50 %.
    $buyer = User::factory()->create();
    Enrollment::factory()->for($buyer)->for($course)->create(['module_id' => $moduleA->id]);
    Enrollment::factory()->for($buyer)->for($course)->create(['module_id' => $moduleB->id]);
    LessonProgress::factory()->completed()->create([
        'user_id' => $buyer->id,
        'lesson_id' => $lessonA->id,
    ]);

    // Someone whose access expired is not a sample at all; counted, they would
    // drag the average down with progress they can no longer make.
    Enrollment::factory()->for(User::factory())->for($course)
        ->create(['expires_at' => now()->subDay()]);

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    expect($response->json('data.completion_rate'))->toBe(50);
});
