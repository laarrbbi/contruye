<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * The quizzes and the final exam are graded on the server. A module's quiz is
 * paid content like its lessons; the exam is for students with the whole
 * course; the answer key only leaves the server after a module quiz is
 * submitted, and never for the exam.
 */
function quizCourse(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    [$owned, $locked] = Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id]);

    return [$course, $owned, $locked];
}

function studentOwning(Module $module): User
{
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $module->course_id, 'module_id' => $module->id]);

    return $student;
}

it('gives a module quiz to the owners of its module, without the answers', function () {
    [, $owned, $locked] = quizCourse();
    $quiz = Quiz::factory()->forModule($owned)->create();
    $lockedQuiz = Quiz::factory()->forModule($locked)->create();
    $student = studentOwning($owned);

    $this->getJson("/api/quizzes/{$quiz->id}")->assertUnauthorized();

    $response = $this->actingAs($student)->getJson("/api/quizzes/{$quiz->id}")->assertOk();

    expect($response->json('data.questions'))->toHaveCount(4)
        ->and($response->json('data.questions.0.opciones.0'))->toBe(['letra' => 'A', 'texto' => 'Opción A de la pregunta 1'])
        ->and($response->getContent())->not->toContain('correcta');

    // The quiz of a module the student has not bought stays closed, at every door.
    $this->actingAs($student)->getJson("/api/quizzes/{$lockedQuiz->id}")->assertForbidden();
    $this->actingAs($student)->postJson("/api/quizzes/{$lockedQuiz->id}/attempts")->assertForbidden();
});

it('grades on the server, keeps the attempt, and then shows the right answers', function () {
    [, $owned] = quizCourse();
    $quiz = Quiz::factory()->forModule($owned)->create();
    $student = studentOwning($owned);

    $attemptId = $this->actingAs($student)->postJson("/api/quizzes/{$quiz->id}/attempts")
        ->assertCreated()->json('data.open_attempt.id');

    // Three right of four: the pass mark is 3.
    $result = $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", [
        'answers' => ['1' => 'B', '2' => 'B', '3' => 'B', '4' => 'A'],
    ])->assertOk()->json('data');

    expect($result['score'])->toBe(3)
        ->and($result['total'])->toBe(4)
        ->and($result['passed'])->toBeTrue()
        ->and($result['results'][3])->toBe(['numero' => 4, 'answer' => 'A', 'is_correct' => false, 'correct_answer' => 'B']);

    $attempt = QuizAttempt::query()->findOrFail($attemptId);
    expect($attempt->score)->toBe(3)->and($attempt->submitted_at)->not->toBeNull();

    // One submission per attempt.
    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => ['1' => 'B']])
        ->assertStatus(409);

    // The player shows the result on the module.
    $module = collect($this->actingAs($student)->getJson("/api/courses/{$owned->course->slug}/learn")->json('data.modules'))
        ->firstWhere('id', $owned->id);
    expect($module['quiz'])->toMatchArray(['passed' => true, 'best_score' => 3, 'attempts_count' => 1]);
});

it('fails an attempt below the pass mark, and refuses answers to questions that do not exist', function () {
    [, $owned] = quizCourse();
    $quiz = Quiz::factory()->forModule($owned)->create();
    $student = studentOwning($owned);

    $attemptId = $this->actingAs($student)->postJson("/api/quizzes/{$quiz->id}/attempts")->json('data.open_attempt.id');

    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => ['9' => 'B']])
        ->assertUnprocessable()->assertJsonValidationErrors('answers');
    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => ['1' => 'Z']])
        ->assertUnprocessable();

    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => ['1' => 'B', '2' => 'C']])
        ->assertOk()->assertJsonPath('data.score', 1)->assertJsonPath('data.passed', false);
});

it('does not let one student submit another student\'s attempt', function () {
    [, $owned] = quizCourse();
    $quiz = Quiz::factory()->forModule($owned)->create();
    $alice = studentOwning($owned);
    $bob = studentOwning($owned);

    $attemptId = $this->actingAs($alice)->postJson("/api/quizzes/{$quiz->id}/attempts")->json('data.open_attempt.id');

    $this->actingAs($bob)->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => ['1' => 'B']])
        ->assertNotFound();
});

it('keeps the final exam for students with the whole course', function () {
    [$course, $owned] = quizCourse();
    $exam = Quiz::factory()->finalExam($course)->create();

    $partial = studentOwning($owned);
    $this->actingAs($partial)->getJson("/api/quizzes/{$exam->id}")->assertForbidden();
    $this->actingAs($partial)->postJson("/api/quizzes/{$exam->id}/attempts")->assertForbidden();

    $full = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $full->id, 'course_id' => $course->id, 'module_id' => null]);

    // The questions only come with a running attempt, so the clock is honest.
    $this->actingAs($full)->getJson("/api/quizzes/{$exam->id}")->assertOk()
        ->assertJsonPath('data.questions', null)
        ->assertJsonPath('data.duration_minutes', 60);
});

it('times the final exam, resumes it on reload, and never reveals its key', function () {
    [$course] = quizCourse();
    $exam = Quiz::factory()->finalExam($course)->create();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $this->travelTo(now()->startOfMinute());
    $started = $this->actingAs($student)->postJson("/api/quizzes/{$exam->id}/attempts")->assertCreated()->json('data');
    expect($started['open_attempt']['expires_at'])->toBe(now()->addMinutes(60)->toIso8601String())
        ->and($started['questions'])->toHaveCount(4);

    // Reloading the page later resumes the same attempt and the same deadline.
    $this->travel(10)->minutes();
    $resumed = $this->actingAs($student)->postJson("/api/quizzes/{$exam->id}/attempts")->json('data.open_attempt');
    expect($resumed['id'])->toBe($started['open_attempt']['id'])
        ->and($resumed['expires_at'])->toBe($started['open_attempt']['expires_at']);

    $result = $this->actingAs($student)->postJson("/api/quiz-attempts/{$resumed['id']}/submit", [
        'answers' => ['1' => 'B', '2' => 'B', '3' => 'B', '4' => 'B'],
    ])->assertOk()->json('data');

    expect($result['passed'])->toBeTrue()
        ->and(collect($result['results'])->pluck('correct_answer')->unique()->all())->toBe([null]);
});

it('closes an exam answered after its time as failed, without grading it', function () {
    [$course] = quizCourse();
    $exam = Quiz::factory()->finalExam($course)->create();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $attemptId = $this->actingAs($student)->postJson("/api/quizzes/{$exam->id}/attempts")->json('data.open_attempt.id');

    // Past the 60 minutes the attempt is no longer running…
    $this->travel(61)->minutes();
    $this->actingAs($student)->getJson("/api/quizzes/{$exam->id}")->assertJsonPath('data.open_attempt', null);

    // …and past the two minutes of grace for a slow connection, an answer
    // sheet closes it as failed instead of being graded.
    $this->travel(2)->minutes();
    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", [
        'answers' => ['1' => 'B', '2' => 'B', '3' => 'B', '4' => 'B'],
    ])->assertUnprocessable();

    $attempt = QuizAttempt::query()->findOrFail($attemptId);
    expect($attempt->passed)->toBeFalse()
        ->and($attempt->score)->toBe(0)
        ->and($attempt->submitted_at)->not->toBeNull();
});

it('still grades an exam that arrives within the grace after its deadline', function () {
    [$course] = quizCourse();
    $exam = Quiz::factory()->finalExam($course)->create();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $attemptId = $this->actingAs($student)->postJson("/api/quizzes/{$exam->id}/attempts")->json('data.open_attempt.id');

    // The browser submits at 0:00; the request lands a minute late.
    $this->travel(61)->minutes();
    $this->actingAs($student)->postJson("/api/quiz-attempts/{$attemptId}/submit", [
        'answers' => ['1' => 'B', '2' => 'B', '3' => 'B', '4' => 'B'],
    ])->assertOk()->assertJsonPath('data.score', 4);
});
