<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = User::query()
            ->where('role', 'student')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', Like::contains($search))
                    ->orWhere('email', 'like', Like::contains($search))
                    ->orWhere('phone', 'like', Like::contains($search)));
            })
            ->withCount('enrollments')
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $students->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'enrollments_count' => $user->enrollments_count,
                'created_at' => $user->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->role === 'student', 404);

        $user->load(['enrollments.course.modules.lessons', 'orders.course']);

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->flip();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'created_at' => $user->created_at?->toISOString(),
            // One row per course, measured over the modules the student can
            // actually open. Per-module buyers used to get one row per module,
            // each reading "0 / 34 leçons" against the whole course.
            'enrollments' => $user->enrollments->groupBy('course_id')->map(function ($rows) use ($completedLessonIds) {
                $first = $rows->sortBy('created_at')->first();
                $course = $first->course;
                $wholeCourse = $rows->contains(fn ($row) => $row->module_id === null);
                $ownedIds = $wholeCourse
                    ? $course->modules->pluck('id')
                    : $rows->pluck('module_id')->filter()->unique()->intersect($course->modules->pluck('id'));

                $lessons = $course->modules->whereIn('id', $ownedIds)->flatMap->lessons;
                $done = $lessons->filter(fn ($lesson) => isset($completedLessonIds[$lesson->id]))->count();

                return [
                    'id' => $first->id,
                    'course' => [
                        'id' => $course->id,
                        'slug' => $course->slug,
                        'title' => $course->localizedTitle(),
                    ],
                    'full_access' => $course->modules->isNotEmpty() && $ownedIds->count() >= $course->modules->count(),
                    'owned_modules_count' => $ownedIds->count(),
                    'total_modules_count' => $course->modules->count(),
                    // Null when any row is open-ended; otherwise the latest end.
                    'expires_at' => $rows->contains(fn ($row) => $row->expires_at === null)
                        ? null
                        : $rows->max('expires_at')?->toISOString(),
                    'progress_percent' => $lessons->count() > 0 ? (int) round($done / $lessons->count() * 100) : 0,
                    'completed_lessons' => $done,
                    'total_lessons' => $lessons->count(),
                    'created_at' => $first->created_at?->toISOString(),
                ];
            })->values(),
            'orders' => $user->orders->map(fn ($order) => [
                'id' => $order->id,
                'course_title' => $order->course?->localizedTitle(),
                'amount' => $order->amount,
                'currency' => $order->currency,
                'method' => $order->method,
                'status' => $order->status,
                'created_at' => $order->created_at?->toISOString(),
            ])->values(),
        ]]);
    }
}
