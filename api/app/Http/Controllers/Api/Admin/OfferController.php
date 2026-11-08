<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\UserOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The three offers, as the admin writes them: what each sells, when it shows,
 * and how many students it reached, saw it, clicked and closed it.
 */
class OfferController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = UserOffer::query()
            ->selectRaw('stage, count(*) as reached, count(emailed_at) as emailed, count(seen_at) as seen, count(clicked_at) as clicked, count(dismissed_at) as dismissed')
            ->groupBy('stage')->get()->keyBy('stage');

        $offers = Offer::query()->get()
            ->sortBy(fn (Offer $offer) => array_search($offer->stage, Offer::STAGES, true))
            ->map(fn (Offer $offer) => $this->payload($offer) + [
                'stats' => collect(['reached', 'emailed', 'seen', 'clicked', 'dismissed'])
                    ->mapWithKeys(fn (string $key) => [$key => (int) ($stats->get($offer->stage)?->{$key} ?? 0)]),
            ]);

        return response()->json(['data' => $offers->values()]);
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        $rules = [
            'is_active' => ['required', 'boolean'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'url' => ['nullable', 'url:http,https', 'max:500', 'prohibits:course_id'],
            'price_eur' => ['nullable', 'integer', 'min:0', 'max:1000000', 'prohibits:course_id'],
            'delay_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'available_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'modules_required' => ['required', 'integer', 'min:1', 'max:13'],
            'send_email' => ['required', 'boolean'],
        ];
        foreach (config('locales.supported') as $locale) {
            $rules["title_{$locale}"] = ['nullable', 'string', 'max:160'];
            $rules["body_{$locale}"] = ['nullable', 'string', 'max:5000'];
            $rules["cta_label_{$locale}"] = ['nullable', 'string', 'max:80'];
        }
        $data = $request->validate($rules);

        // Switched on, it must say something and lead somewhere.
        if ($data['is_active']) {
            $errors = [];
            if (blank($data['title_es'] ?? null)) {
                $errors['title_es'] = __('An active offer needs a title in Spanish.');
            }
            if (blank($data['course_id'] ?? null) && blank($data['url'] ?? null)) {
                $errors['url'] = __('An active offer needs a course or a link to sell.');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $offer->update($data);
        AuditLog::record($request->user(), 'offer.updated', $offer, ['stage' => $offer->stage, 'is_active' => $offer->is_active]);

        return response()->json(['data' => $this->payload($offer->refresh())]);
    }

    private function payload(Offer $offer): array
    {
        $fields = ['id', 'stage', 'is_active', 'course_id', 'url', 'price_eur', 'delay_hours', 'available_hours', 'modules_required', 'send_email'];
        foreach (config('locales.supported') as $locale) {
            array_push($fields, "title_{$locale}", "body_{$locale}", "cta_label_{$locale}");
        }

        return $offer->only($fields) + [
            'is_live' => $offer->isLive(),
            'activated_at' => $offer->activated_at?->toIso8601String(),
        ];
    }
}
