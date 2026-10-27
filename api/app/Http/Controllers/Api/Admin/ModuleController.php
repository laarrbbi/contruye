<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleRequest;
use App\Http\Requests\Admin\ReorderModulesRequest;
use App\Http\Resources\AdminModuleResource;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    public function store(ModuleRequest $request, Course $course): JsonResponse
    {
        $module = $course->modules()->create([
            ...$request->validated(),
            'order' => ((int) $course->modules()->max('order')) + 1,
        ]);

        return AdminModuleResource::make($module)
            ->response()
            ->setStatusCode(201);
    }

    public function update(ModuleRequest $request, Module $module): AdminModuleResource
    {
        // Per-module prices feed every à-la-carte quote.
        $before = $module->only(['title_es', 'price_eur']);
        $module->update($request->validated());

        AuditLog::record($request->user(), 'module.updated', $module, [
            'before' => $before,
            'after' => $module->only(array_keys($before)),
        ]);

        return AdminModuleResource::make($module);
    }

    public function destroy(Request $request, Module $module): Response|JsonResponse
    {
        // An issued invoice is built from these order lines: deleting the
        // module would leave the invoice unable to describe what was sold.
        if (DB::table('order_modules')->where('module_id', $module->id)->exists()) {
            return response()->json([
                'message' => __('This module has been sold and cannot be deleted. Remove it from the programme instead.'),
            ], 422);
        }

        AuditLog::record($request->user(), 'module.deleted', $module, ['title' => $module->title_es]);

        $module->delete();

        return response()->noContent();
    }

    public function reorder(ReorderModulesRequest $request, Course $course): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $course) {
            foreach ($request->validated('module_ids') as $index => $id) {
                $course->modules()->whereKey($id)->update(['order' => $index + 1]);
            }
        });

        return AdminModuleResource::collection($course->modules()->get());
    }
}
