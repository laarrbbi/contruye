<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\Course;
use App\Models\CustomTask;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's roadmap: per-module curated checklists plus their own tasks.
 *
 * Same access rules as the player — checklist TEXTS distil paid content, so a
 * locked module only ever exposes how many steps it holds, never what they say.
 */
class ChecklistController extends Controller
{
    use AuthorizesCourseAccess;

    public function index(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $this->authorizeCourseAccess($user, $course);

        $course->load(['modules.checklistItems']);

        $accessibleIds = $user->isAdmin()
            ? $course->modules->pluck('id')
            : $user->accessibleModuleIds($course);

        $checkedIds = $user->checkedChecklistItems()->pluck('checklist_items.id')->flip();

        $tasksByModule = CustomTask::query()
            ->where('user_id', $user->id)
            ->whereIn('module_id', $course->modules->pluck('id'))
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->groupBy('module_id');

        return response()->json(['data' => [
            'modules' => $course->modules->map(function (Module $module) use ($accessibleIds, $checkedIds, $tasksByModule) {
                $accessible = $accessibleIds->contains($module->id);
                $items = $module->checklistItems->sortBy('order')->values();

                return [
                    'id' => $module->id,
                    'order' => $module->order,
                    'title' => $module->localizedTitle(),
                    'is_locked' => ! $accessible,
                    'items_count' => $items->count(),
                    // Paid content: texts only for owned modules.
                    'items' => $accessible
                        ? $items->map(fn (ChecklistItem $item) => [
                            'id' => $item->id,
                            'text' => $item->localizedText(),
                            'checked' => isset($checkedIds[$item->id]),
                        ])->values()
                        : [],
                    'custom_tasks' => $accessible
                        ? ($tasksByModule->get($module->id) ?? collect())->map(fn (CustomTask $task) => [
                            'id' => $task->id,
                            'body' => $task->body,
                            'is_done' => $task->is_done,
                        ])->values()
                        : [],
                ];
            })->values(),
        ]]);
    }

    /** Check or uncheck a curated step. Idempotent. */
    public function toggle(Request $request, ChecklistItem $item): JsonResponse
    {
        $user = $request->user();
        $module = $item->module;

        abort_if($module === null, 404);
        abort_unless($user->isAdmin() || $user->hasAccessToModule($module), 403);

        $checked = $request->validate(['checked' => ['required', 'boolean']])['checked'];

        if ($checked) {
            $item->checkedBy()->syncWithoutDetaching([$user->id]);
        } else {
            $item->checkedBy()->detach($user->id);
        }

        return response()->json(['data' => ['id' => $item->id, 'checked' => $checked]]);
    }

    public function storeTask(Request $request, Module $module): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->hasAccessToModule($module), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        // A personal list, not a database dump — keep it human-sized.
        abort_if(
            CustomTask::query()->where('user_id', $user->id)->where('module_id', $module->id)->count() >= 100,
            422,
            __('Task limit reached for this module.'),
        );

        $task = CustomTask::create([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'body' => $data['body'],
            'order' => (int) CustomTask::query()
                ->where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->max('order') + 1,
        ]);

        return response()->json(['data' => [
            'id' => $task->id,
            'body' => $task->body,
            'is_done' => $task->is_done,
        ]], 201);
    }

    public function updateTask(Request $request, CustomTask $task): JsonResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['sometimes', 'string', 'max:500'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $task->update($data);

        return response()->json(['data' => [
            'id' => $task->id,
            'body' => $task->body,
            'is_done' => $task->is_done,
        ]]);
    }

    public function destroyTask(Request $request, CustomTask $task): JsonResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $task->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
