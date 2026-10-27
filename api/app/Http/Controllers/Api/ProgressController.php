<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\LessonCompleteRequest;
use App\Http\Requests\ProgressHeartbeatRequest;
use App\Http\Resources\LessonProgressResource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Periodic player heartbeat: upsert the resume position.
     * Never touches completed_at.
     */
    public function heartbeat(ProgressHeartbeatRequest $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        $this->authorizeLessonAccess($user, $lesson, allowFreePreview: true);

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['last_position_seconds' => $request->validated('position_seconds')],
        );

        return $this->progressResponse($progress);
    }

    /**
     * Mark a lesson complete (idempotent) or, with {completed: false},
     * un-complete it.
     */
    public function complete(LessonCompleteRequest $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        $this->authorizeLessonAccess($user, $lesson, allowFreePreview: true);

        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
        );

        if ($request->boolean('completed', true)) {
            // Idempotent: keep the original completion timestamp.
            if ($progress->completed_at === null) {
                $progress->update(['completed_at' => now()]);
            }
        } else {
            $progress->update(['completed_at' => null]);
        }

        return $this->progressResponse($progress);
    }

    /**
     * Both endpoints upsert, so force 200: resources default to 201
     * when the underlying model was just created.
     */
    private function progressResponse(LessonProgress $progress): JsonResponse
    {
        return LessonProgressResource::make($progress)
            ->response()
            ->setStatusCode(200);
    }
}
