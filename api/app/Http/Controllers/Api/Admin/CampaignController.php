<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promotions admin. A campaign always carries a real end date; the countdown
 * students see is that timestamp, and the discount stops applying once it
 * passes.
 */
class CampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = Campaign::query()->with('course')->latest()->get();

        return response()->json([
            'data' => $campaigns->map(fn (Campaign $campaign) => $this->payload($campaign)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $campaign = Campaign::create($this->validated($request, requireFutureEnd: true));

        AuditLog::record($request->user(), 'campaign.created', $campaign);

        // Reload so DB defaults (is_active) are reflected in the response.
        return response()->json(['data' => $this->payload($campaign->refresh()->load('course'))], 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign->update($this->validated($request));

        AuditLog::record($request->user(), 'campaign.updated', $campaign);

        return response()->json(['data' => $this->payload($campaign->load('course'))]);
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        AuditLog::record($request->user(), 'campaign.deleted', $campaign);
        $campaign->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireFutureEnd = false): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'label_es' => ['required', 'string', 'max:120'],
            // Optional, like every English field: the Spanish name shows meanwhile.
            'label_en' => ['nullable', 'string', 'max:120'],
            // Capped below 100: a promotion discounts a price, it doesn't
            // give the course away, and it can never produce a negative total.
            'percent_off' => ['required', 'integer', 'min:1', 'max:90'],
            // An inverted window would silently never run.
            'starts_at' => ['nullable', 'date', 'before:ends_at'],
            // Always required — a promotion must have a real end date. It must
            // be in the future when CREATED, but a finished campaign stays
            // editable (renaming it, disabling it) without having to
            // resurrect the discount just to pass validation.
            'ends_at' => array_filter(['required', 'date', $requireFutureEnd ? 'after:now' : null]),
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'course' => [
                'id' => $campaign->course->id,
                'title' => $campaign->course->localizedTitle(),
            ],
            'label_es' => $campaign->label_es,
            'label_en' => $campaign->label_en,
            'percent_off' => $campaign->percent_off,
            'starts_at' => $campaign->starts_at?->toISOString(),
            'ends_at' => $campaign->ends_at->toISOString(),
            'is_active' => $campaign->is_active,
            'is_running' => $campaign->isRunning(),
            'created_at' => $campaign->created_at?->toISOString(),
        ];
    }
}
