<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;

function publishedCourse(): Course
{
    return Course::factory()->create(['status' => 'published']);
}

function enrolledStudent(Course $course): User
{
    $user = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);

    return $user;
}

it('reports no rating at all until a real review is approved', function () {
    $course = publishedCourse();

    // A pending review must not move the public numbers.
    Review::factory()->create(['course_id' => $course->id, 'rating' => 5]);

    $response = $this->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    expect($response->json('data'))->toHaveCount(0)
        ->and($response->json('meta.count'))->toBe(0)
        ->and($response->json('meta.average'))->toBeNull(); // never a fabricated score

    $this->getJson("/api/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.rating', null)
        ->assertJsonPath('data.reviews_count', 0);
});

it('publishes only approved reviews and averages exactly those', function () {
    $course = publishedCourse();

    Review::factory()->approved()->create(['course_id' => $course->id, 'rating' => 5]);
    Review::factory()->approved()->create(['course_id' => $course->id, 'rating' => 4]);
    Review::factory()->create(['course_id' => $course->id, 'rating' => 1]);            // pending
    Review::factory()->rejected()->create(['course_id' => $course->id, 'rating' => 1]); // rejected

    $response = $this->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.count'))->toBe(2)
        ->and($response->json('meta.average'))->toBe(4.5)
        ->and($response->json('meta.distribution.5'))->toBe(1)
        ->and($response->json('meta.distribution.4'))->toBe(1)
        ->and($response->json('meta.distribution.1'))->toBe(0);

    $this->getJson("/api/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.rating', 4.5)
        ->assertJsonPath('data.reviews_count', 2);
});

it('never exposes reviewer email addresses', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);
    Review::factory()->approved()->create([
        'course_id' => $course->id,
        'user_id' => $student->id,
        'rating' => 5,
    ]);

    $response = $this->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    expect($response->json('data.0.author_name'))->toBe($student->name)
        ->and($response->json('data.0.verified_purchase'))->toBeTrue()
        ->and(json_encode($response->json()))->not->toContain($student->email);
});

it('lets only enrolled students review, and holds it for moderation', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);

    $this->actingAs($student)
        ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 5, 'body' => 'Excellente formation.'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    // Not public until approved.
    $this->getJson("/api/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.count', 0);

    $this->assertDatabaseHas('reviews', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'rating' => 5,
        'status' => 'pending',
    ]);
});

it('rejects reviews from non-buyers and guests', function () {
    $course = publishedCourse();

    $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 5])
        ->assertUnauthorized();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 5])
        ->assertForbidden();

    $this->assertDatabaseCount('reviews', 0);
});

it('validates the rating range', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);

    foreach ([0, 6, -1] as $rating) {
        $this->actingAs($student)
            ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => $rating])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }
});

it('keeps one review per student and sends edits back to moderation', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);

    $this->actingAs($student)
        ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 4])
        ->assertCreated();

    Review::where('user_id', $student->id)->update(['status' => 'approved', 'approved_at' => now()]);

    // Editing an approved review un-publishes it until re-approved.
    $this->actingAs($student)
        ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 2, 'body' => 'Revu à la baisse.'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(Review::where('user_id', $student->id)->count())->toBe(1);

    $this->getJson("/api/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.count', 0);
});

it('lets an admin approve and reject reviews but not author them', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);
    $review = Review::factory()->create([
        'course_id' => $course->id,
        'user_id' => $student->id,
        'rating' => 5,
        'body' => 'Très complet.',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin/reviews')
        ->assertOk()
        ->assertJsonPath('meta.pending_count', 1);

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/moderate", ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // Now public, with the student's own words unchanged.
    $this->getJson("/api/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.body', 'Très complet.');

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/moderate", ['status' => 'rejected', 'admin_note' => 'Hors sujet'])
        ->assertOk();

    $this->getJson("/api/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.count', 0);
});

it('forbids review moderation for non-admins', function () {
    $course = publishedCourse();
    $review = Review::factory()->create(['course_id' => $course->id]);
    $student = enrolledStudent($course);

    $this->actingAs($student)->getJson('/api/admin/reviews')->assertForbidden();
    $this->actingAs($student)
        ->postJson("/api/admin/reviews/{$review->id}/moderate", ['status' => 'approved'])
        ->assertForbidden();
});

it('shows an author their own review while it is still waiting for moderation', function () {
    $course = publishedCourse();
    $student = enrolledStudent($course);

    $this->actingAs($student)
        ->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 4, 'body' => 'En attente de modération.'])
        ->assertCreated();

    $response = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    // my_review is how the SPA stops offering "laisser un avis" to someone who
    // already wrote one, and how it pre-fills the edit form. The review is not
    // public yet — meta.count stays 0 — but its author must see it landed.
    expect($response->json('meta.can_review'))->toBeTrue()
        ->and($response->json('meta.my_review.body'))->toBe('En attente de modération.')
        ->and($response->json('meta.my_review.status'))->toBe('pending')
        ->and($response->json('meta.count'))->toBe(0)
        ->and($response->json('data'))->toHaveCount(0);
});

it('never hands a viewer someone else\'s pending review, nor an invitation to write one', function () {
    $course = publishedCourse();
    $author = enrolledStudent($course);

    Review::factory()->create([
        'course_id' => $course->id,
        'user_id' => $author->id,
        'rating' => 1,
        'body' => 'Brouillon privé en cours de modération.',
    ]);

    // Guest first: the sanctum guard caches a resolved user for the rest of
    // the process, so an anonymous read has to happen before any actingAs().
    $guest = $this->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    expect($guest->json('meta.can_review'))->toBeFalse()
        ->and($guest->json('meta.my_review'))->toBeNull()
        ->and(json_encode($guest->json()))->not->toContain('Brouillon privé');

    // A signed-in stranger who bought nothing is not invited either — the
    // button appears only for someone whose enrolment can back the rating.
    $stranger = User::factory()->create();
    $response = $this->actingAs($stranger)->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    expect($response->json('meta.can_review'))->toBeFalse()
        ->and($response->json('meta.my_review'))->toBeNull()
        ->and(json_encode($response->json()))->not->toContain('Brouillon privé');
});

it('counts only real active enrolments as students', function () {
    $course = publishedCourse();

    enrolledStudent($course);
    enrolledStudent($course);

    // An expired enrolment is not an active student.
    $expired = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $expired->id,
        'course_id' => $course->id,
        'module_id' => null,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson("/api/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.students_count', 2);
});

it('pages the review list while the summary still counts every review — C10', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $ratings = array_merge(array_fill(0, 18, 5), array_fill(0, 5, 3)); // 23 reviews

    foreach ($ratings as $rating) {
        Review::factory()->approved()->create(['course_id' => $course->id, 'rating' => $rating]);
    }
    Review::factory()->create(['course_id' => $course->id, 'rating' => 1, 'status' => 'pending']);

    $first = $this->getJson("/api/courses/{$course->slug}/reviews")->assertOk();

    // Ten per page, but the score is over all 23 approved reviews:
    // (18 × 5 + 5 × 3) / 23 = 4.565… → 4.6. The pending one counts nowhere.
    expect($first->json('data'))->toHaveCount(10)
        ->and($first->json('meta.count'))->toBe(23)
        ->and($first->json('meta.average'))->toEqual(4.6)
        ->and($first->json('meta.distribution'))->toEqual(['1' => 0, '2' => 0, '3' => 5, '4' => 0, '5' => 18])
        ->and($first->json('meta.last_page'))->toBe(3);

    $last = $this->getJson("/api/courses/{$course->slug}/reviews?page=3")->assertOk();
    expect($last->json('data'))->toHaveCount(3);

    // A caller cannot ask for the whole table in one go: 53 reviews, 50 max.
    Review::factory()->approved()->count(30)->create(['course_id' => $course->id, 'rating' => 4]);
    $greedy = $this->getJson("/api/courses/{$course->slug}/reviews?per_page=1000")->assertOk();

    expect($greedy->json('data'))->toHaveCount(50)
        ->and($greedy->json('meta.count'))->toBe(53)
        ->and($greedy->json('meta.last_page'))->toBe(2);
});
