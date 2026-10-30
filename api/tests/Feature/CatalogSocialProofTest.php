<?php

use App\Models\Campaign;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Review;
use App\Models\User;

/**
 * The catalog card for one slug out of GET /api/courses.
 *
 * The index builds its social proof with correlated subqueries, a different
 * implementation from the per-course path GET /api/courses/{slug} uses. Every
 * existing social-proof assertion in the suite targets the detail endpoint, so
 * these tests deliberately go through the index and nothing else.
 *
 * @return array<string, mixed>
 */
function catalogCard(string $slug): array
{
    return collect(test()->getJson('/api/courses')->assertOk()->json('data'))
        ->firstWhere('slug', $slug);
}

it('counts a student once, an expired enrolment never, and only approved reviews', function () {
    $course = Course::factory()->create(['slug' => 'proof-course']);
    $neighbour = Course::factory()->create(['slug' => 'neighbour-course']);

    $modules = Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id]);

    // Amina bought two modules à la carte: two enrollment rows, one student.
    $amina = User::factory()->create();
    foreach ($modules as $module) {
        Enrollment::factory()->for($amina)->for($course)
            ->create(['module_id' => $module->id]);
    }

    // Karim holds full access — a second, distinct student.
    Enrollment::factory()->for(User::factory())->for($course)->create();

    // Sonia's access lapsed. She is no longer a student of this course, and
    // counting her would inflate the catalog with people who cannot log in.
    Enrollment::factory()->for(User::factory())->for($course)
        ->create(['expires_at' => now()->subDay()]);

    Review::factory()->approved()->for($course)->create(['rating' => 4]);
    Review::factory()->approved()->for($course)->create(['rating' => 5]);
    // Written but not moderated, and one an admin refused: neither is public,
    // so neither may move the score.
    Review::factory()->for($course)->create(['rating' => 1]);
    Review::factory()->rejected()->for($course)->create(['rating' => 1]);
    // Approved, but on the course next door — the subquery is correlated.
    Review::factory()->approved()->for($neighbour)->create(['rating' => 1]);
    Review::factory()->approved()->for($neighbour)->create(['rating' => 2]);
    Enrollment::factory()->for(User::factory())->for($neighbour)->create();

    $card = catalogCard('proof-course');

    expect($card['students_count'])->toBe(2)
        ->and($card['reviews_count'])->toBe(2)
        ->and($card['rating'])->toBe(4.5);

    // The neighbour reports its own numbers, not the sum of both.
    $other = catalogCard('neighbour-course');

    expect($other['students_count'])->toBe(1)
        ->and($other['reviews_count'])->toBe(2)
        ->and($other['rating'])->toBe(1.5);
});

it('shows no rating at all rather than a placeholder before a review is approved', function () {
    $course = Course::factory()->create(['slug' => 'unreviewed-course']);
    Review::factory()->for($course)->create(['rating' => 5]);

    $card = catalogCard('unreviewed-course');

    // A zero, or the pending review's 5, would both be fabricated proof.
    expect($card['rating'])->toBeNull()
        ->and($card['reviews_count'])->toBe(0)
        ->and($card['students_count'])->toBe(0);
});

it('promotes a campaign on the catalog only while it is genuinely running', function () {
    $running = Course::factory()->create(['slug' => 'running-course', 'price_eur' => 90000]);
    $notYet = Course::factory()->create(['slug' => 'not-yet-course', 'price_eur' => 90000]);

    Campaign::factory()->create(['course_id' => $running->id, 'percent_off' => 15]);
    // Three campaigns that must not show: not started, finished, switched off.
    Campaign::factory()->upcoming()->create(['course_id' => $running->id, 'percent_off' => 40]);
    Campaign::factory()->expired()->create(['course_id' => $running->id, 'percent_off' => 35]);
    Campaign::factory()->disabled()->create(['course_id' => $running->id, 'percent_off' => 50]);

    // The catalog eager-loads `campaigns`, so this course goes through the
    // in-memory Campaign::isRunning() branch rather than scopeActive().
    expect(catalogCard('running-course')['campaign']['percent_off'])->toBe(15);

    Campaign::factory()->upcoming()->create(['course_id' => $notYet->id, 'percent_off' => 60]);

    // A promotion announced for next week must not be advertised today.
    expect(catalogCard('not-yet-course')['campaign'])->toBeNull();
});

it('resolves the same campaign in memory as the queried path resolves it', function () {
    $course = Course::factory()->create(['slug' => 'overlap-course', 'price_eur' => 90000]);

    Campaign::factory()->create([
        'course_id' => $course->id, 'percent_off' => 10, 'ends_at' => now()->addDays(9),
    ]);
    $best = Campaign::factory()->create([
        'course_id' => $course->id, 'percent_off' => 30, 'ends_at' => now()->addDays(2),
    ]);
    Campaign::factory()->create([
        'course_id' => $course->id, 'percent_off' => 30, 'ends_at' => now()->addDays(8),
    ]);

    $card = catalogCard('overlap-course');

    // Best discount first, soonest deadline to break the tie: the countdown a
    // student sees must not depend on which endpoint drew the card.
    $queried = Course::query()->findOrFail($course->id)->activeCampaign();

    expect($card['campaign']['percent_off'])->toBe(30)
        ->and($card['campaign']['ends_at'])->toBe($best->ends_at->toISOString())
        ->and($queried->id)->toBe($best->id)
        ->and($card['campaign']['ends_at'])->toBe($queried->ends_at->toISOString());
});
