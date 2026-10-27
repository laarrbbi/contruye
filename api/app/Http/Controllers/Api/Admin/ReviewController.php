<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Review moderation. Admins approve or reject what students wrote — they
 * cannot author or edit review content, so the public rating always reflects
 * real buyers.
 */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);

        $query = Review::query()->with(['user', 'course'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $reviews = $query->paginate($perPage);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'body' => $review->body,
                'status' => $review->status,
                'admin_note' => $review->admin_note,
                'user' => [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'email' => $review->user->email,
                ],
                'course' => [
                    'id' => $review->course->id,
                    'title' => $review->course->localizedTitle(),
                ],
                'created_at' => $review->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'pending_count' => Review::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        AuditLog::record($request->user(), "review.{$validated['status']}", $review);

        return response()->json(['data' => [
            'id' => $review->id,
            'status' => $review->status,
        ]]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        AuditLog::record($request->user(), 'review.deleted', $review);
        $review->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
