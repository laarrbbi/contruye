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

        // The student's language when the lesson has that version, Spanish otherwise.
        $video = $lesson->videoFor(app()->getLocale());
        abort_if($video === null, 404);

        $bunny = filled(config('services.bunny.library_id')) && filled(config('services.bunny.token_key'));
        $files = config('services.video_files.base');

        // Bunny Stream not configured: a file of the preview's, behind the same gate, or nothing.
        if (! $bunny && blank($files)) {
            abort(503, __('Video playback is temporarily unavailable. Please try again later.'));
        }

        $signed = $bunny
            ? ['type' => 'embed', ...BunnyStreamService::fromConfig()->signedEmbedUrl($video['id'])]
            : ['type' => 'file', 'url' => rtrim((string) $files, '/').'/'.rawurlencode($video['id']).'.mp4', 'expires_at' => null];

        return response()->json([
            'data' => [
                // 'embed': Bunny's player in an iframe; 'file': a video file.
                'type' => $signed['type'],
                'url' => $signed['url'],
                'expires_at' => $signed['expires_at'],
                // The language the video is spoken in, for the player to say when it is not the student's.
                'locale' => $video['locale'],
                // Shown as an overlay by the player to discourage screen recording.
                'watermark' => $user?->email,
            ],
        ]);
    }
}
