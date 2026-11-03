<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The company directory, for the admin: find companies, and choose which ones
 * students see — one by one, or every company a filter finds.
 */
class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->filtered($request);
        $page = (clone $query)->with('province:id,code,name_es')->orderBy('name')->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'category' => $company->category,
                'activity_code' => $company->activity_code,
                'city' => $company->city,
                'province' => $company->province?->name_es,
                'phone' => $company->phone,
                'website' => $company->website,
                'email' => $company->email,
                'tax_id' => $company->tax_id,
                'employees' => $company->employees,
                'source' => $company->source,
                'is_visible' => $company->is_visible,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'visible' => (clone $query)->where('is_visible', true)->count(),
                'categories' => array_keys(config('directory.categories')),
            ],
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate(['is_visible' => ['required', 'boolean']]);
        $company->update($validated);
        AuditLog::record($request->user(), $company->is_visible ? 'company.shown' : 'company.hidden', $company);

        return response()->json(['data' => ['id' => $company->id, 'is_visible' => $company->is_visible]]);
    }

    /** POST /admin/companies/visibility — show or hide every company the filter finds. */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate(['is_visible' => ['required', 'boolean']]);
        $visible = $request->boolean('is_visible');
        $count = $this->filtered($request)->where('is_visible', '!=', $visible)->update(['is_visible' => $visible, 'updated_at' => now()]);
        Company::forgetCounts();
        AuditLog::record($request->user(), $visible ? 'companies.shown' : 'companies.hidden', null, [
            'count' => $count,
            'filters' => $request->only(['q', 'category', 'province', 'visible']),
        ]);

        return response()->json(['data' => ['updated' => $count]]);
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys(config('directory.categories')))],
            'province' => ['nullable', 'integer'],
            'visible' => ['nullable', 'in:0,1'],
        ]);

        return Company::query()
            ->search($request->input('q'))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->filled('province'), fn (Builder $q) => $q->where('province_id', $request->integer('province')))
            ->when($request->filled('visible'), fn (Builder $q) => $q->where('is_visible', $request->input('visible') === '1'));
    }
}
