<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * The student's dashboard: one entry per course with the access summary
     * (full access vs owned modules) and progress computed over the lessons
     * the student can actually watch.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with('course')
            ->active()
            ->latest()
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $courseIds = $enrollments->pluck('course_id')->unique();

        $modulesByCourse = Module::query()
            ->whereIn('course_id', $courseIds)
            ->get(['id', 'course_id', 'kind'])
            ->groupBy('course_id');

        $lessonTotalsByModule = Lesson::query()
            ->whereIn('module_id', $modulesByCourse->flatten()->pluck('id'))
            ->groupBy('module_id')
            ->selectRaw('module_id, count(*) as aggregate')
            ->pluck('aggregate', 'module_id');

        $completedByModule = LessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->groupBy('lessons.module_id')
            ->selectRaw('lessons.module_id, count(*) as aggregate')
            ->pluck('aggregate', 'module_id');

        // The courses whose final exam this student passed: the course is finished.
        $finishedCourseIds = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $user->id)
            ->where('quiz_attempts.passed', true)
            ->where('quizzes.kind', 'final')
            ->whereIn('quizzes.course_id', $courseIds)
            ->distinct()
            ->pluck('quizzes.course_id');

        $data = $enrollments
            ->groupBy('course_id')
            ->map(function ($rows) use ($modulesByCourse, $lessonTotalsByModule, $completedByModule, $finishedCourseIds) {
                $first = $rows->sortBy('created_at')->first();
                $course = $first->course;

                $courseModules = $modulesByCourse->get($course->id, collect());
                // The course's own modules: Financials is the extra, in no plan.
                $includedIds = $courseModules->reject(fn (Module $m) => $m->isFinancials())->pluck('id');
                $wholeCourse = $rows->contains(fn ($row) => $row->module_id === null);
                $boughtIds = $rows->pluck('module_id')->filter()->unique()->intersect($courseModules->pluck('id'));

                $ownedModuleIds = ($wholeCourse ? $includedIds->merge($boughtIds) : $boughtIds)->unique()->values();

                // Same rule as User::hasFullAccessTo(): every included module is
                // watchable, however it was bought.
                $fullAccess = $includedIds->isNotEmpty()
                    && $ownedModuleIds->intersect($includedIds)->count() >= $includedIds->count();

                $total = (int) $ownedModuleIds->sum(fn ($id) => (int) ($lessonTotalsByModule[$id] ?? 0));
                $done = (int) $ownedModuleIds->sum(fn ($id) => (int) ($completedByModule[$id] ?? 0));

                return [
                    'id' => $first->id,
                    'course' => [
                        'id' => $course->id,
                        'slug' => $course->slug,
                        'title' => $course->localizedTitle(),
                        'cover_image' => $course->cover_image,
                        'progress_percent' => $total === 0 ? 0 : (int) round($done / $total * 100),
                    ],
                    'full_access' => $fullAccess,
                    'finished' => $finishedCourseIds->contains($course->id),
                    'owned_modules_count' => $ownedModuleIds->intersect($includedIds)->count(),
                    'total_modules_count' => $includedIds->count(),
                    'expires_at' => $first->expires_at?->toISOString(),
                    'created_at' => $first->created_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }
}
