<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybackController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Signed Bunny embed URL for a lesson.
     *
     * Deliberately not behind auth middleware: free-preview lessons are
     * playable by guests, everything else requires an enrolled (or admin)
     * authenticated user — enforced here.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $lesson->is_free_preview) {
            abort_if($user === null, 401);
            $this->authorizeLessonAccess($user, $lesson);
        }

        abort_if(blank($lesson->bunny_video_id), 404);

        if (blank(config('services.bunny.library_id')) || blank(config('services.bunny.token_key'))) {
            abort(503, __('Video playback is temporarily unavailable. Please try again later.'));
        }

        $signed = BunnyStreamService::fromConfig()->signedEmbedUrl($lesson->bunny_video_id);

        return response()->json([
            'data' => [
                'url' => $signed['url'],
                'expires_at' => $signed['expires_at'],
                // Shown as an overlay by the player to discourage screen recording.
                'watermark' => $user?->email,
            ],
        ]);
    }
}
