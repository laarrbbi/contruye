<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The quiz that closes each module and the final exam.
 *
 * - A module quiz is for the owners of its module; the final exam is for
 *   students with the whole course.
 * - The questions go out without their answers. The answer key never leaves
 *   the server before an attempt is submitted; grading happens here.
 * - The final exam is timed: an attempt carries its deadline from the moment
 *   it starts, and an answer sheet that arrives after it (plus a short grace
 *   for the network) is closed as failed, not graded.
 * - After a module quiz the student sees the right answers, to learn from
 *   them. After the exam they see their score and which answers were wrong,
 *   not the key: the exam can be retaken.
 */
class QuizController extends Controller
{
    use AuthorizesCourseAccess;

    /** Seconds accepted after an exam's deadline, for a slow connection. */
    public const GRACE_SECONDS = 120;

    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($user, $quiz);

        $open = $this->openAttempt($user, $quiz);

        return response()->json(['data' => [
            ...$this->meta($quiz),
            // A module quiz can be read at any time; the exam's questions
            // only come with a running attempt, so its clock is honest.
            'questions' => $quiz->isFinal() ? ($open ? $quiz->questionsForStudent() : null) : $quiz->questionsForStudent(),
            'open_attempt' => $open ? $this->attemptMeta($open) : null,
            'attempts' => $this->history($user, $quiz),
        ]]);
    }

    public function start(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($user, $quiz);

        // Starting again resumes the running attempt: reloading the exam page
        // must not reset its clock.
        $attempt = $this->openAttempt($user, $quiz) ?? QuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'total' => $quiz->questionCount(),
            'started_at' => now(),
            'expires_at' => $quiz->duration_minutes ? now()->addMinutes($quiz->duration_minutes) : null,
        ]);

        return response()->json(['data' => [
            ...$this->meta($quiz),
            'questions' => $quiz->questionsForStudent(),
            'open_attempt' => $this->attemptMeta($attempt),
        ]], 201);
    }

    public function submit(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($attempt->user_id === $user->id, 404);

        $quiz = $attempt->quiz;
        $this->authorizeQuiz($user, $quiz);

        $answers = $request->validate([
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'string', 'in:A,B,C,D,E'],
        ])['answers'];

        $key = $quiz->answerKey();
        $unknown = array_diff(array_map('intval', array_keys($answers)), array_keys($key));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['answers' => [__('Some answers do not match a question of this quiz.')]]);
        }

        $late = $attempt->expires_at !== null
            && now()->greaterThan($attempt->expires_at->copy()->addSeconds(self::GRACE_SECONDS));

        // One submission per attempt, even when two arrive at once.
        $graded = DB::transaction(function () use ($attempt, $answers, $key, $quiz, $late) {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->submitted_at !== null) {
                return null;
            }

            $score = $late ? 0 : count(array_filter(
                $key,
                fn (string $letter, int $number) => ($answers[$number] ?? $answers[(string) $number] ?? null) === $letter,
                ARRAY_FILTER_USE_BOTH,
            ));

            $locked->update([
                'answers' => $late ? [] : $answers,
                'score' => $score,
                'passed' => ! $late && $score >= $quiz->pass_mark,
                'submitted_at' => now(),
            ]);

            return $locked;
        });

        if ($graded === null) {
            abort(409, __('This attempt has already been submitted.'));
        }

        if ($late) {
            throw ValidationException::withMessages(['answers' => [__('The time for this exam is over. The attempt has been closed.')]]);
        }

        return response()->json(['data' => [
            ...$this->attemptMeta($graded),
            'results' => array_map(fn (int $number, string $letter) => [
                'numero' => $number,
                'answer' => $answers[$number] ?? $answers[(string) $number] ?? null,
                'is_correct' => ($answers[$number] ?? $answers[(string) $number] ?? null) === $letter,
                // The key is shown after a module quiz, never after the exam.
                'correct_answer' => $quiz->isFinal() ? null : $letter,
            ], array_keys($key), $key),
        ]]);
    }

    private function authorizeQuiz(User $user, Quiz $quiz): void
    {
        if ($quiz->isFinal()) {
            $this->authorizeCourseAccess($user, $quiz->course);
            $this->authorizeFullAccess($user, $quiz->course);

            return;
        }

        abort_if($quiz->module === null, 404);
        $this->authorizeModuleAccess($user, $quiz->module);
    }

    private function openAttempt(User $user, Quiz $quiz): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereNull('submitted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(Quiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'kind' => $quiz->kind,
            'module_id' => $quiz->module_id,
            'title' => $quiz->localizedTitle(),
            'pass_mark' => $quiz->pass_mark,
            'questions_count' => $quiz->questionCount(),
            'duration_minutes' => $quiz->duration_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptMeta(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'started_at' => $attempt->started_at->toIso8601String(),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'score' => $attempt->score,
            'total' => $attempt->total,
            'passed' => $attempt->passed,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(User $user, Quiz $quiz): array
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (QuizAttempt $attempt) => $this->attemptMeta($attempt))
            ->all();
    }
}
