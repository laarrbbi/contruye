<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\LearnCourseResource;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;

class LearnController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Course-player payload: modules/lessons in order plus the
     * authenticated user's progress (enrolled students or admins only).
     */
    public function show(Request $request, Course $course): LearnCourseResource
    {
        $user = $request->user();

        $this->authorizeCourseAccess($user, $course);

        $course->load([
            'modules.lessons.contentBlocks',
            'modules.contentBlocks',
            'modules.partners.provinces',
            'modules.quiz',
            'documents',
            'quizzes',
        ]);

        // One query for all of the user's progress rows in this course.
        $lessonIds = $course->modules->flatMap->lessons->pluck('id');
        $progressByLesson = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $accessibleModuleIds = $user->isAdmin()
            ? $course->modules->pluck('id')
            : $user->accessibleModuleIds($course);

        // The student's submitted quiz attempts, for the pass / best-score badges.
        $attemptsByQuiz = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('quiz_id', $course->quizzes->pluck('id'))
            ->whereNotNull('submitted_at')
            ->get()
            ->groupBy('quiz_id');

        return new LearnCourseResource($course, $progressByLesson, $accessibleModuleIds, $attemptsByQuiz);
    }
}
