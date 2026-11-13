<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\LegalParameters;
use App\Services\ReferenceValues;
use App\Support\Course\CourseFiles;
use App\Support\Financials\TemplateCatalog;
use App\Support\Financials\XlsxFiller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads of the course: the template files for the owners of Financials,
 * the province directory and the course PDF for students with the whole
 * course, and the instructor's templates of a module for its owners. Served
 * as delivered (CourseFiles), but for a corrected template, which carries the
 * legal parameters in force like every template a student downloads.
 */
class CourseFileController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        private readonly LegalParameters $legal,
        private readonly ReferenceValues $reference,
    ) {}

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

        if ($file['legal'] ?? false) {
            return response()->download($this->withLegalParameters($path), $file['name'], [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        return response()->download($path, $file['name'], [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A copy of a corrected template with the legal parameters in force in its
     * «Parámetros legales» sheet and the 2002 tariffs the admin has brought up
     * to date in their cells: what the platform's calculator applies. Its own
     * data, notes and formulas travel untouched.
     */
    private function withLegalParameters(string $path): string
    {
        $cells = TemplateCatalog::fromConfig()->legalCells();

        return XlsxFiller::fill($path, [
            ...$this->reference->tariffsForEngine(),
            $cells['sheet'] => $this->legal->cellValues($cells),
        ]);
    }
}
