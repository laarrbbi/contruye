<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The course-level texts (introduction, resources, annexes). Any student of
 * the course reads the `enrolled` ones; a `full` one needs the whole course.
 */
class CourseDocumentController extends Controller
{
    use AuthorizesCourseAccess;

    public function show(Request $request, Course $course, string $slug): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        $document = $course->documents()->where('slug', $slug)->firstOrFail();

        if ($document->access === 'full') {
            $this->authorizeFullAccess($user, $course);
        }

        return response()->json(['data' => [
            'slug' => $document->slug,
            'title' => $document->localizedTitle(),
            'access' => $document->access,
            'body_html' => Markdown::toHtml($document->localizedBody()),
            'body_locale' => $document->localizedLocale('body'),
        ]]);
    }
}
