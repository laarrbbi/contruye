<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Course reviews. Public reads expose only approved reviews plus the rating
 * summary; writing requires an active enrolment, so every published rating
 * comes from a real buyer.
 */
class ReviewController extends Controller
{
    /**
     * Approved reviews + rating summary for a course. Public.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        // The summary covers every approved review, counted in SQL; only the
        // list itself is paged. C10: this public endpoint returned every
        // approved review, unpaginated, to anyone.
        $byRating = $course->reviews()->approved()
            ->selectRaw('rating, COUNT(*) AS n')
            ->groupBy('rating')
            ->pluck('n', 'rating');

        $count = (int) $byRating->sum();

        // Distribution keyed 1..5 so the UI can draw the histogram without
        // inventing buckets for ratings nobody gave.
        $distribution = collect(range(1, 5))
            ->mapWithKeys(fn (int $star) => [$star => (int) ($byRating[$star] ?? 0)]);

        $page = $course->reviews()->approved()->with('user')
            ->latest()->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        $user = $request->user('sanctum');

        // The viewer's own review — including one still pending moderation, so
        // they can see it was received and edit it.
        $mine = $user === null
            ? null
            : $course->reviews()->where('user_id', $user->id)->with('user')->first();

        return response()->json([
            'data' => ReviewResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'count' => $count,
                // Null (not 0) when there is nothing to average — the UI shows
                // an honest empty state rather than a fabricated score.
                'average' => $count === 0
                    ? null
                    : round($distribution->map(fn (int $n, int $star) => $n * $star)->sum() / $count, 1),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'distribution' => $distribution,
                'can_review' => $user !== null && $user->isEnrolledIn($course),
                'my_review' => $mine === null ? null : ReviewResource::make($mine)->resolve(),
            ],
        ]);
    }

    /**
     * Create or update the authenticated student's review. Re-editing an
     * approved review sends it back to moderation.
     */
    public function store(ReviewRequest $request, Course $course): JsonResponse
    {
        $user = $request->user();

        abort_unless($course->status === 'published', 404);

        if (! $user->isEnrolledIn($course)) {
            return response()->json([
                'message' => __('Only students enrolled in this course can review it.'),
            ], 403);
        }

        $review = Review::query()->updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'rating' => (int) $request->validated('rating'),
                'body' => $request->validated('body'),
                'status' => 'pending',
                'approved_at' => null,
            ],
        );

        return response()->json([
            'data' => ReviewResource::make($review->load('user'))->resolve(),
        ], 201);
    }
}
