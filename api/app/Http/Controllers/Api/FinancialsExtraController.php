<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\FinancialsExtra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The extra at the end of the course (the professional template), as a
 * student's pages offer it: on sale or not, its price, and whether they have
 * it. For the students of the course, whatever their plan.
 */
class FinancialsExtraController extends Controller
{
    use AuthorizesCourseAccess;

    public function show(Request $request, Course $course, FinancialsExtra $extra): JsonResponse
    {
        $this->authorizeCourseAccess($request->user(), $course);

        return response()->json(['data' => $extra->view($course, $request->user())]);
    }
}
