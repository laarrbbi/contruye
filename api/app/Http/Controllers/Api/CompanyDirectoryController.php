<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company directory, for students.
 *
 * A module's companies are those of the categories its lessons send the
 * promoter to (config/directory.php), and they follow the module's gate:
 * contact details are part of what a module sells. The Partners page lists
 * the companies of every module the student owns, with the others named and
 * locked. Only companies the admin left visible are listed.
 */
class CompanyDirectoryController extends Controller
{
    use AuthorizesCourseAccess;

    private const PER_PAGE = 24;

    /** GET /courses/{course}/modules/{module}/companies */
    public function module(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless($module->course_id === $course->id && $module->kind === 'phase', 404);
        $this->authorizeModuleAccess($request->user(), $module);

        return $this->listing($request, Company::categoriesForPhase($module->order));
    }

    /** GET /courses/{course}/companies — the Partners page */
    public function index(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        $accessible = $user->isAdmin() ? $course->modules()->pluck('id') : $user->accessibleModuleIds($course);
        $phases = $course->modules()->where('kind', 'phase')->orderBy('order')->get()
            ->map(fn (Module $module) => [
                'module_id' => $module->id,
                'phase' => $module->order,
                'title' => $module->localizedTitle(),
                'is_locked' => ! $accessible->contains($module->id),
            ]);

        $open = $phases->where('is_locked', false);
        $chosen = $request->integer('module');
        if ($chosen) {
            $open = $open->where('module_id', $chosen);
        }
        $categories = $open->flatMap(fn (array $phase) => Company::categoriesForPhase($phase['phase']))->unique()->values()->all();

        return $this->listing($request, $categories, ['phases' => $phases->values()]);
    }

    /** @param list<string> $categories */
    private function listing(Request $request, array $categories, array $extra = []): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:40'],
            'province' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $base = fn (): Builder => Company::query()->visible()->whereIn('category', $categories)
            ->when($request->filled('province'), fn (Builder $q) => $q->where('province_id', $request->integer('province')))
            ->search($request->input('q'));

        // How many in each category, for the filter: before the category itself is applied.
        $counts = $base()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');
        $category = $request->input('category');
        $query = $base()->when($category && in_array($category, $categories, true), fn (Builder $q) => $q->where('category', $category))
            ->with('province:id,code,name_es,name_en,name_fr,name_ar,name_ru,name_uk')
            // Larger firms first: a promoter's first calls, then the alphabet.
            ->orderByRaw('employees is null')->orderByDesc('employees')->orderBy('name');
        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'category' => $company->category,
                'legal_form' => $company->legal_form,
                'address' => $company->address,
                'postal_code' => $company->postal_code,
                'city' => $company->city,
                'province' => $company->province?->localizedName(),
                'phone' => $company->phone,
                'email' => $company->email,
                'website' => $company->website,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'categories' => collect($categories)->map(fn (string $key) => ['key' => $key, 'count' => (int) ($counts[$key] ?? 0)])->values(),
            ],
            ...$extra,
        ]);
    }
}
