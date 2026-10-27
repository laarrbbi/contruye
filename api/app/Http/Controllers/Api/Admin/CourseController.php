<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\AdminCourseResource;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->withCount(['modules', 'lessons', 'enrollments'])
            ->latest()
            ->get();

        return AdminCourseResource::collection($courses);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title_es']);
        }

        $course = Course::create($data);
        $course->refresh(); // pick up DB defaults (status)

        AuditLog::record($request->user(), 'course.created', $course, ['slug' => $course->slug]);

        return AdminCourseResource::make($course)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Course $course): AdminCourseResource
    {
        $course->load(['modules.lessons', 'modules.partners', 'modules.contentBlocks'])
            ->loadCount(['modules', 'lessons', 'enrollments']);

        return AdminCourseResource::make($course);
    }

    public function update(UpdateCourseRequest $request, Course $course): AdminCourseResource
    {
        $data = $request->validated();

        // A null/absent slug on update means "keep the current one".
        if (empty($data['slug'])) {
            unset($data['slug']);
        }

        // Price and publication state are the two levers worth attributing.
        $before = $course->only(['slug', 'status', 'price_eur']);
        $course->update($data);

        AuditLog::record($request->user(), 'course.updated', $course, [
            'before' => $before,
            'after' => $course->only(array_keys($before)),
        ]);

        return AdminCourseResource::make($course);
    }

    public function destroy(Request $request, Course $course): Response|JsonResponse
    {
        // Orders are financial records: a catalogue edit must never destroy
        // one. The foreign keys enforce this too — this is the readable half.
        if ($course->orders()->exists()) {
            return response()->json([
                'message' => __('This course has been sold and cannot be deleted. Unpublish it instead.'),
            ], 422);
        }

        AuditLog::record($request->user(), 'course.deleted', $course, ['slug' => $course->slug]);

        $course->delete();

        return response()->noContent();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
