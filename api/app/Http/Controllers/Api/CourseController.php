<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Services\CourseSocialProof;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Social proof as correlated subqueries rather than three queries per
        // course: the catalog stays at a constant query count as it grows.
        $courses = Course::published()
            ->withCount('lessons')
            ->withSum('lessons', 'duration_seconds')
            ->with('campaigns')
            ->addSelect([
                'students_count' => Enrollment::query()
                    ->selectRaw('count(distinct user_id)')
                    ->whereColumn('enrollments.course_id', 'courses.id')
                    ->active(),
                'reviews_count' => Review::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('reviews.course_id', 'courses.id')
                    ->approved(),
                'rating' => Review::query()
                    ->selectRaw('avg(rating)')
                    ->whereColumn('reviews.course_id', 'courses.id')
                    ->approved(),
            ])
            ->latest()
            ->get();

        // Catalog cards must show the same promotional price as the landing
        // page and the checkout quote. `campaigns` is eager-loaded, so this
        // resolves in memory.
        $pricing = app(PricingService::class);
        $courses->each(fn (Course $course) => $course->setAttribute(
            'campaign',
            $pricing->campaignPayload($course->activeCampaign()),
        ));

        return CourseResource::collection($courses);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        abort_unless($course->status === 'published', 404);

        $course->loadCount('lessons')
            ->loadSum('lessons', 'duration_seconds')
            ->load('modules.lessons');

        CourseSocialProof::attach($course);

        $user = $request->user('sanctum');
        $ownedModuleIds = $user?->accessibleModuleIds($course) ?? collect();

        $course->setAttribute('is_enrolled', $user !== null && $user->isEnrolledIn($course));
        $course->setAttribute('full_access', $user !== null && $user->hasFullAccessTo($course));
        $course->modules->each(
            fn ($module) => $module->setAttribute('is_owned', $ownedModuleIds->contains($module->id)),
        );

        return CourseResource::make($course);
    }
}
