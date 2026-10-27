<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Http\Requests\Admin\ReorderLessonsRequest;
use App\Http\Resources\AdminLessonResource;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function store(LessonRequest $request, Module $module): JsonResponse
    {
        $lesson = $module->lessons()->create([
            ...$request->validated(),
            'order' => ((int) $module->lessons()->max('order')) + 1,
        ]);
        $lesson->refresh(); // pick up DB defaults (duration_seconds, is_free_preview)

        return AdminLessonResource::make($lesson)
            ->response()
            ->setStatusCode(201);
    }

    public function update(LessonRequest $request, Lesson $lesson): AdminLessonResource
    {
        $lesson->update($request->validated());

        return AdminLessonResource::make($lesson);
    }

    public function destroy(Request $request, Lesson $lesson): Response
    {
        AuditLog::record($request->user(), 'lesson.deleted', $lesson, [
            'title' => $lesson->title_fr,
            'module_id' => $lesson->module_id,
        ]);

        $lesson->delete();

        return response()->noContent();
    }

    public function reorder(ReorderLessonsRequest $request, Module $module): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $module) {
            foreach ($request->validated('lesson_ids') as $index => $id) {
                $module->lessons()->whereKey($id)->update(['order' => $index + 1]);
            }
        });

        return AdminLessonResource::collection($module->lessons()->get());
    }
}
