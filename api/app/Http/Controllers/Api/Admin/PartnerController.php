<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Partner;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in(Partner::CATEGORIES)],
            'wilaya_id' => ['sometimes', 'nullable', 'integer', 'exists:wilayas,id'],
        ]);

        $wilayaId = $filters['wilaya_id'] ?? null;
        $search = $filters['search'] ?? null;

        $partners = Partner::query()
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($wilayaId, fn ($q, $id) => $q->inWilaya($id))
            ->when($search, fn ($q, $search) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', Like::contains($search))
                ->orWhereHas('wilayas', fn ($w) => $w
                    ->where('name_fr', 'like', Like::contains($search))
                    ->orWhere('name_ar', 'like', Like::contains($search)))))
            ->with('wilayas')
            ->withCount('modules')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $partners->map(fn (Partner $partner) => $this->payload($partner)),
            // When a wilaya is being inspected, say which service categories
            // have nobody there — that is the admin's actual question.
            'meta' => $wilayaId === null ? null : ['gaps' => $this->categoryGaps($wilayaId)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $partner = Partner::create($data);
        $partner->wilayas()->sync($data['is_national'] ?? false ? [] : ($data['wilaya_ids'] ?? []));

        AuditLog::record($request->user(), 'partner.created', $partner);

        return response()->json(['data' => $this->payload($partner->fresh('wilayas'))], 201);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $data = $this->validated($request);
        $hasList = array_key_exists('wilaya_ids', $data);

        // Naming actual wilayas means explicit coverage — otherwise a partner
        // that used to be national would silently swallow the new list.
        $isNational = array_key_exists('is_national', $data)
            ? (bool) $data['is_national']
            : ($hasList && $data['wilaya_ids'] !== [] ? false : $partner->is_national);

        $partner->update($data + ['is_national' => $isNational]);

        // A national partner keeps no explicit rows: coverage lives in the
        // flag alone, so the two can never disagree.
        if ($hasList || array_key_exists('is_national', $data)) {
            $partner->wilayas()->sync($isNational ? [] : ($data['wilaya_ids'] ?? []));
        }

        AuditLog::record($request->user(), 'partner.updated', $partner);

        return response()->json(['data' => $this->payload($partner->fresh('wilayas'))]);
    }

    /**
     * Service categories with no active partner in this wilaya (national ones
     * count as present everywhere).
     *
     * @return list<string>
     */
    private function categoryGaps(int $wilayaId): array
    {
        $covered = Partner::query()
            ->active()
            ->inWilaya($wilayaId)
            ->distinct()
            ->pluck('category')
            ->all();

        return array_values(array_diff(Partner::CATEGORIES, $covered));
    }

    public function destroy(Request $request, Partner $partner): JsonResponse
    {
        AuditLog::record($request->user(), 'partner.deleted', $partner);
        $partner->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Replace the partner list attached to a module, in display order.
     */
    public function syncModule(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'partners' => ['present', 'array'],
            'partners.*.partner_id' => ['required', 'integer', 'exists:partners,id'],
            'partners.*.note_fr' => ['nullable', 'string', 'max:255'],
            'partners.*.note_ar' => ['nullable', 'string', 'max:255'],
        ]);

        $sync = [];
        // Re-index first: the display order must come from the list's position,
        // never from client-supplied array keys (which may be sparse or
        // non-numeric).
        foreach (array_values($data['partners']) as $index => $row) {
            // Later duplicates of the same partner collapse onto one pivot row.
            $sync[$row['partner_id']] = [
                'order' => $index + 1,
                'note_fr' => $row['note_fr'] ?? null,
                'note_ar' => $row['note_ar'] ?? null,
            ];
        }

        $module->partners()->sync($sync);

        AuditLog::record($request->user(), 'module.partners_synced', $module);

        return response()->json([
            'data' => $module->load('partners')->partners->map(fn (Partner $partner) => [
                'partner_id' => $partner->id,
                'name' => $partner->name,
                'category' => $partner->category,
                'order' => $partner->pivot->order,
                'note_fr' => $partner->pivot->note_fr,
                'note_ar' => $partner->pivot->note_ar,
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Partner::CATEGORIES)],
            'description_fr' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_national' => ['sometimes', 'boolean'],
            'wilaya_ids' => ['sometimes', 'array'],
            'wilaya_ids.*' => ['integer', 'exists:wilayas,id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'category' => $partner->category,
            'description_fr' => $partner->description_fr,
            'description_ar' => $partner->description_ar,
            'website' => $partner->website,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'is_national' => $partner->is_national,
            'wilayas' => $partner->wilayas->map(fn ($wilaya) => [
                'id' => $wilaya->id,
                'code' => $wilaya->code,
                'name' => $wilaya->localizedName(),
            ])->values(),
            'is_active' => $partner->is_active,
            'modules_count' => $partner->modules_count ?? $partner->modules()->count(),
            'created_at' => $partner->created_at?->toISOString(),
        ];
    }
}
