<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;

/**
 * The written text of a free-preview lesson, for a visitor on the sales
 * page: the sample a buyer reads before paying. Only lessons marked
 * `is_free_preview` of a published course are served; any other lesson is
 * not a preview and answers 404, whoever asks (constitution III: paid
 * content stays behind the paywall, and is read in the player).
 */
class LessonPreviewController extends Controller
{
    public function show(Lesson $lesson): JsonResponse
    {
        $lesson->loadMissing('module.course');
        abort_unless($lesson->is_free_preview && $lesson->module?->course?->status === 'published', 404);

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->localizedTitle(),
                'content_html' => Markdown::toHtml($lesson->localizedContent(), glossary: true),
                // The player says so when the text is still the Spanish original.
                'content_locale' => $lesson->localizedLocale('content'),
            ],
        ]);
    }
}
