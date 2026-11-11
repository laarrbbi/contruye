<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Course\CourseFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads of the course: the template files for the owners of Financials,
 * the province directory and the course PDF for students with the whole
 * course, and the instructor's templates of a module for its owners. Served
 * as delivered (CourseFiles).
 */
class CourseFileController extends Controller
{
    use AuthorizesCourseAccess;

    public function download(Request $request, Course $course, string $key): BinaryFileResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        $file = CourseFiles::find($key);
        abort_if($file === null, 404);

        if ($file['access'] === 'full') {
            $this->authorizeFullAccess($user, $course);
        } else {
            $module = $file['access'] === 'module'
                ? $course->modules()->where('kind', 'phase')->where('order', $file['module'])->first()
                : $course->modules()->where('kind', 'financials')->first();
            abort_if($module === null, 404);
            $this->authorizeModuleAccess($user, $module);
        }

        $path = CourseFiles::path($file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file['name'], [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
