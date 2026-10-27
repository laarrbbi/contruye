<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Stream a lesson attachment by its array index. The client only ever
     * sees {name, index} — the storage path stays server-side.
     */
    public function download(Request $request, Lesson $lesson, int $index): StreamedResponse
    {
        $this->authorizeLessonAccess($request->user(), $lesson);

        $attachments = array_values($lesson->attachments ?? []);

        abort_unless(isset($attachments[$index]), 404);

        $path = $attachments[$index]['path'] ?? null;
        $name = $attachments[$index]['name'] ?? basename((string) $path);

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }
}
