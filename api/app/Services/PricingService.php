<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for per-module pricing, volume-discount tiers and
 * full-access pricing. Used by the public quote endpoint AND checkout, so the
 * price a student sees is always the price they are charged.
 */
class PricingService
{
    /** The one currency the academy sells in. */
    public const CURRENCY = 'EUR';

    /** Default volume tiers when the course doesn't define its own. */
    public const DEFAULT_TIERS = [
        ['min_modules' => 2, 'percent_off' => 5],
        ['min_modules' => 3, 'percent_off' => 10],
        ['min_modules' => 5, 'percent_off' => 15],
        ['min_modules' => 7, 'percent_off' => 20],
    ];

    /**
     * Build a full pricing quote.
     *
     * Every price is in euros: courses and modules carry one price each.
     *
     * @param  'full'|'modules'  $scope
     * @param  int[]  $moduleIds
     * @return array{
     *   scope: string, currency: string,
     *   items: list<array{module_id: int, title: string, unit_price: int}>,
     *   subtotal: int, tier_percent: int, tier_discount: int,
     *   campaign: ?array{label: string, percent_off: int, ends_at: string},
     *   campaign_percent: int, campaign_discount: int,
     *   coupon_code: ?string, coupon_percent: int, coupon_discount: int,
     *   total: int, full_price: int,
     *   full_price_anchor: int, savings_vs_itemized: int,
     *   upgrade_hint: ?array{modules_missing: int, price_delta: int},
     *   owned_module_ids: list<int>, upgraded_to_full: bool
     * }
     */
    public function quote(
        Course $course,
        ?User $user,
        string $scope,
        array $moduleIds = [],
        ?Coupon $coupon = null,
    ): array {
        $currency = self::CURRENCY;
        $priceField = 'price_eur';

        $fullPrice = (int) $course->{$priceField};

        $modules = $course->modules()->get();
        $ownedModuleIds = $user ? $this->ownedModuleIds($user, $course) : collect();

        // Resolved once, server-side: the quote a student is shown and the
        // amount they are charged always use the same promotion state.
        $campaign = $course->activeCampaign();

        // Anchor: what ALL modules would cost one by one, no discount.
        $anchor = (int) $modules->sum(fn (Module $m) => $m->{$priceField} ?? 0);

        if ($scope === 'modules') {
            $selection = $modules->whereIn('id', array_unique($moduleIds))->values();

            if ($selection->count() !== count(array_unique($moduleIds))) {
                throw ValidationException::withMessages([
                    'module_ids' => [__('Some selected modules do not belong to this course.')],
                ]);
            }

            $selection = $selection->reject(fn (Module $m) => $ownedModuleIds->contains($m->id))->values();

            if ($selection->isEmpty()) {
                throw ValidationException::withMessages([
                    'module_ids' => [__('You already own the selected modules.')],
                ]);
            }

            // Does the selection cover every module the student doesn't yet own?
            // If so, they are buying the rest of the course, and full access is a
            // valid way to price it — including full-access-only modules that have
            // no à-la-carte price.
            $remainingIds = $modules->pluck('id')->reject(fn ($id) => $ownedModuleIds->contains($id));
            $coversAllRemaining = $remainingIds->diff($selection->pluck('id'))->isEmpty();

            // A partial (non-completing) selection must be individually sellable;
            // a full-only module can only be bought via full access.
            if (! $coversAllRemaining) {
                $notSellable = $selection->first(fn (Module $m) => $m->{$priceField} === null);
                if ($notSellable !== null) {
                    throw ValidationException::withMessages([
                        'module_ids' => [__('Some modules are only available with full access.')],
                    ]);
                }
            }

            $fullQuote = $this->fullQuote($course, $modules, $anchor, $fullPrice, $currency, $coupon, $ownedModuleIds, upgraded: true, campaign: $campaign);

            // "Best price wins": full access covers any selection, so a student must
            // never pay more than full. If the selection can't be itemized (it
            // includes a full-only module, only reachable when it completes the
            // course) or the itemized total meets/exceeds full access, charge full.
            if ($selection->contains(fn (Module $m) => $m->{$priceField} === null)) {
                return $fullQuote;
            }

            $modulesQuote = $this->modulesQuote($course, $selection, $modules, $anchor, $fullPrice, $currency, $priceField, $coupon, $ownedModuleIds, $campaign);

            return $modulesQuote['total'] >= $fullQuote['total'] ? $fullQuote : $modulesQuote;
        }

        if ($user !== null && $user->hasFullAccessTo($course)) {
            throw ValidationException::withMessages([
                'course_id' => [__('You are already enrolled in this course.')],
            ]);
        }

        return $this->fullQuote($course, $modules, $anchor, $fullPrice, $currency, $coupon, $ownedModuleIds, upgraded: false, campaign: $campaign);
    }

    /**
     * The audit trail of a price: what it started from, what each promotion
     * took off, and what was actually owed.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public static function snapshot(array $quote): array
    {
        return [
            'currency' => $quote['currency'],
            'subtotal' => $quote['subtotal'],
            'tier_percent' => $quote['tier_percent'],
            'tier_discount' => $quote['tier_discount'],
            'campaign_label' => $quote['campaign']['label'] ?? null,
            'campaign_percent' => $quote['campaign_percent'],
            'campaign_discount' => $quote['campaign_discount'],
            'coupon_code' => $quote['coupon_code'],
            'coupon_percent' => $quote['coupon_percent'],
            'coupon_discount' => $quote['coupon_discount'],
            'total' => $quote['total'],
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    public function tiersFor(Course $course): array
    {
        $tiers = $course->module_discount_tiers ?: self::DEFAULT_TIERS;

        usort($tiers, fn ($a, $b) => $a['min_modules'] <=> $b['min_modules']);

        return $tiers;
    }

    public function tierPercent(Course $course, int $moduleCount): int
    {
        // Best applicable discount for the buyer — max percent among reached
        // tiers, so a non-monotonic (admin-edited) tier table can't undercut it.
        $percent = 0;
        foreach ($this->tiersFor($course) as $tier) {
            if ($moduleCount >= $tier['min_modules']) {
                $percent = max($percent, (int) $tier['percent_off']);
            }
        }

        return $percent;
    }

    private function modulesQuote(
        Course $course,
        Collection $selection,
        Collection $allModules,
        int $anchor,
        int $fullPrice,
        string $currency,
        string $priceField,
        ?Coupon $coupon,
        Collection $ownedModuleIds,
        ?Campaign $campaign,
    ): array {
        $items = $selection->map(fn (Module $m) => [
            'module_id' => $m->id,
            'title' => $m->localizedTitle(),
            'unit_price' => (int) $m->{$priceField},
        ])->all();

        $subtotal = (int) array_sum(array_column($items, 'unit_price'));
        $tierPercent = $this->tierPercent($course, count($items));
        $afterTier = (int) round($subtotal * (100 - $tierPercent) / 100);
        $tierDiscount = $subtotal - $afterTier;

        $promo = $this->applyPromotions($campaign, $coupon, $afterTier);
        ['coupon_percent' => $couponPercent, 'coupon_discount' => $couponDiscount, 'total' => $total] = $promo;

        // Compare against what full access actually costs right now — the same
        // promotions apply to it, so an undiscounted $fullPrice would overstate
        // the top-up and mis-trigger the hint during a campaign.
        $fullTotal = $this->applyPromotions($campaign, $coupon, $fullPrice)['total'];

        $upgradeHint = null;
        $missing = $allModules->count() - $ownedModuleIds->count() - count($items);
        if ($missing > 0 && $total >= (int) round($fullTotal * 0.7)) {
            $upgradeHint = [
                'modules_missing' => $missing,
                'price_delta' => max(0, $fullTotal - $total),
            ];
        }

        return [
            'scope' => 'modules',
            'currency' => $currency,
            'items' => $items,
            'subtotal' => $subtotal,
            'tier_percent' => $tierPercent,
            'tier_discount' => $tierDiscount,
            'campaign' => $this->campaignPayload($campaign),
            'campaign_percent' => $promo['campaign_percent'],
            'campaign_discount' => $promo['campaign_discount'],
            'coupon_code' => $coupon?->code,
            'coupon_percent' => $couponPercent,
            'coupon_discount' => $couponDiscount,
            'total' => $total,
            'full_price' => $fullPrice,
            'full_price_anchor' => $anchor,
            'savings_vs_itemized' => $tierDiscount + $promo['campaign_discount'] + $couponDiscount,
            'upgrade_hint' => $upgradeHint,
            'owned_module_ids' => $ownedModuleIds->values()->all(),
            'upgraded_to_full' => false,
        ];
    }

    private function fullQuote(
        Course $course,
        Collection $modules,
        int $anchor,
        int $fullPrice,
        string $currency,
        ?Coupon $coupon,
        Collection $ownedModuleIds,
        bool $upgraded,
        ?Campaign $campaign,
    ): array {
        $promo = $this->applyPromotions($campaign, $coupon, $fullPrice);
        ['coupon_percent' => $couponPercent, 'coupon_discount' => $couponDiscount, 'total' => $total] = $promo;

        return [
            'scope' => 'full',
            'currency' => $currency,
            'items' => [],
            'subtotal' => $fullPrice,
            'tier_percent' => $anchor > $fullPrice ? (int) round((1 - $fullPrice / $anchor) * 100) : 0,
            'tier_discount' => max(0, $anchor - $fullPrice),
            'campaign' => $this->campaignPayload($campaign),
            'campaign_percent' => $promo['campaign_percent'],
            'campaign_discount' => $promo['campaign_discount'],
            'coupon_code' => $coupon?->code,
            'coupon_percent' => $couponPercent,
            'coupon_discount' => $couponDiscount,
            'total' => $total,
            'full_price' => $fullPrice,
            'full_price_anchor' => $anchor,
            'savings_vs_itemized' => max(0, $anchor - $total),
            'upgrade_hint' => null,
            'owned_module_ids' => $ownedModuleIds->values()->all(),
            'upgraded_to_full' => $upgraded,
        ];
    }

    /**
     * Apply the running promotion first, then the coupon on what's left.
     *
     * @return array{campaign_percent: int, campaign_discount: int, coupon_percent: int, coupon_discount: int, total: int}
     */
    private function applyPromotions(?Campaign $campaign, ?Coupon $coupon, int $base): array
    {
        $afterCampaign = $campaign === null ? $base : $campaign->apply($base);
        $campaignDiscount = $base - $afterCampaign;

        $total = $coupon === null ? $afterCampaign : $coupon->apply($afterCampaign);
        $couponDiscount = $afterCampaign - $total;

        return [
            'campaign_percent' => (int) ($campaign?->percent_off ?? 0),
            'campaign_discount' => $campaignDiscount,
            'coupon_percent' => (int) ($coupon?->percent_off ?? 0),
            'coupon_discount' => $couponDiscount,
            'total' => $total,
        ];
    }

    /**
     * The public shape of the running promotion, for the countdown UI.
     * `ends_at` is the real deadline — it never restarts.
     *
     * @return array{label: string, percent_off: int, ends_at: string}|null
     */
    public function campaignPayload(?Campaign $campaign): ?array
    {
        if ($campaign === null) {
            return null;
        }

        return [
            'label' => $campaign->localizedLabel(),
            'percent_off' => (int) $campaign->percent_off,
            'ends_at' => $campaign->ends_at->toISOString(),
        ];
    }

    private function ownedModuleIds(User $user, Course $course): Collection
    {
        $enrollments = $user->enrollments()
            ->where('course_id', $course->id)
            ->active()
            ->get(['module_id']);

        if ($enrollments->contains(fn ($e) => $e->module_id === null)) {
            return $course->modules()->pluck('id');
        }

        return $enrollments->pluck('module_id')->filter()->values();
    }
}
