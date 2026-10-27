<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Printable "ficha de actuación" for a lesson (phase 7 — accidente grave o
 * mortal en obra). The sheet is meant to be printed and posted in the site
 * huts (casetas de obra), so it is offered as a downloadable PDF in addition
 * to the on-screen lesson.
 *
 * Same access gate as the lesson itself: it is paid content.
 */
class ReflexSheetController extends Controller
{
    use AuthorizesCourseAccess;

    public function download(Request $request, Lesson $lesson): Response
    {
        $this->authorizeLessonAccess($request->user(), $lesson, allowFreePreview: true);

        // C56: only lessons the instructor wrote as a ficha de actuación have one.
        // The player offers the download on has_reflex_sheet; any other id
        // used to render a "fiche" out of an ordinary lesson.
        abort_unless($lesson->is_reflex_sheet, 404);

        $module = $lesson->module;
        $course = $module->course;

        $tips = $lesson->contentBlocks
            ->where('type', 'expert_tip')
            ->map(fn ($block) => $block->localizedBody())
            ->filter()
            ->values()
            ->all();

        // Legal references of the lesson and of its module.
        $legal = $lesson->contentBlocks->where('type', 'legal')
            ->merge($module->contentBlocks->where('type', 'legal'))
            ->map(fn ($block) => $block->localizedBody())
            ->filter()
            ->values()
            ->all();

        $pdf = Pdf::loadView('pdf.reflex-sheet', [
            'title' => $lesson->localizedTitle(),
            'courseTitle' => $course->localizedTitle(),
            'moduleOrder' => $module->order,
            'intro' => $lesson->localizedContent(),
            'steps' => $lesson->localizedKeyPoints(),
            'tips' => $tips,
            'legal' => $legal,
        ])->setPaper('a4');

        $filename = 'ficha-actuacion-'.$lesson->id.'.pdf';

        return $pdf->download($filename);
    }
}
