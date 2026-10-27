<?php

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

/**
 * @return array{0: User, 1: Lesson}
 */
$enrolledUserWithLesson = function (): array {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create();
    Enrollment::factory()->for($user)->for($lesson->module->course)->create();

    return [$user, $lesson];
};

it('creates a progress row on first heartbeat', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 90])
        ->assertOk()
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.last_position_seconds', 90)
        ->assertJsonPath('data.is_completed', false);

    $this->assertDatabaseHas('lesson_progress', [
        'user_id' => $user->id,
        'lesson_id' => $lesson->id,
        'last_position_seconds' => 90,
        'completed_at' => null,
    ]);
});

it('updates the position on later heartbeats without touching completed_at', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $progress = LessonProgress::factory()->completed()->for($user)->for($lesson)
        ->create(['last_position_seconds' => 10]);
    $completedAt = $progress->fresh()->completed_at;

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 250])
        ->assertOk()
        ->assertJsonPath('data.last_position_seconds', 250)
        ->assertJsonPath('data.is_completed', true);

    $fresh = $progress->fresh();

    expect(LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->count())->toBe(1)
        ->and($fresh->last_position_seconds)->toBe(250)
        ->and($fresh->completed_at?->equalTo($completedAt))->toBeTrue();
});

it('marks a lesson complete idempotently, keeping the first timestamp', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.is_completed', true);

    $firstCompletedAt = LessonProgress::firstWhere([
        'user_id' => $user->id,
        'lesson_id' => $lesson->id,
    ])->completed_at;

    $this->travel(10)->minutes();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/complete", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.is_completed', true);

    $secondCompletedAt = LessonProgress::firstWhere([
        'user_id' => $user->id,
        'lesson_id' => $lesson->id,
    ])->completed_at;

    expect($secondCompletedAt->equalTo($firstCompletedAt))->toBeTrue();
});

it('clears completed_at when completed is false', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    LessonProgress::factory()->completed()->for($user)->for($lesson)->create();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/complete", ['completed' => false])
        ->assertOk()
        ->assertJsonPath('data.is_completed', false);

    $this->assertDatabaseHas('lesson_progress', [
        'user_id' => $user->id,
        'lesson_id' => $lesson->id,
        'completed_at' => null,
    ]);
});

it('returns 403 for a non-enrolled user on both endpoints', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 10])
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/complete")
        ->assertForbidden();
});

it('returns 401 for guests', function () {
    $lesson = Lesson::factory()->create();

    $this->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 10])
        ->assertUnauthorized();

    $this->postJson("/api/lessons/{$lesson->id}/complete")
        ->assertUnauthorized();
});

it('rejects a negative position with 422', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => -5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position_seconds');
});

it('rejects a missing position with 422', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position_seconds');
});

it('validates position_seconds in both directions — finding C22, closed', function () use ($enrolledUserWithLesson) {
    [$user, $lesson] = $enrolledUserWithLesson();

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 3600])
        ->assertOk()
        ->assertJsonPath('data.last_position_seconds', 3600);

    // A day is beyond any lesson. Past the unsigned column's ceiling this
    // used to be a 500 from the driver; both are now a 422 on the field.
    foreach ([86401, 4294967296] as $tooLarge) {
        $this->actingAs($user)
            ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => $tooLarge])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('position_seconds');
    }

    // The rejected heartbeats leave the student's resume point alone.
    expect(LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->count())->toBe(1)
        ->and(LessonProgress::firstWhere(['user_id' => $user->id, 'lesson_id' => $lesson->id])->last_position_seconds)
        ->toBe(3600);

    $this->actingAs($user)
        ->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 'beaucoup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position_seconds');
});
