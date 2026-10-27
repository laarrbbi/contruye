<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public, localized representation of a course.
 *
 * Expects `lessons_count` / `lessons_sum_duration_seconds` aggregates to be
 * loaded by the controller. `modules` and `is_enrolled` only appear on the
 * detail endpoint.
 */
class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->localizedTitle(),
            'description' => $this->localizedDescription(),
            'price_dzd' => $this->price_dzd,
            'price_eur' => $this->price_eur,
            'module_discount_tiers' => $this->module_discount_tiers
                ?: PricingService::DEFAULT_TIERS,
            'cover_image' => $this->cover_image,
            'lessons_count' => (int) $this->lessons_count,
            'total_duration_seconds' => (int) ($this->lessons_sum_duration_seconds ?? 0),
            // Real social proof: actual paid enrolments and the real average of
            // approved reviews. `rating` is null until a genuine review lands.
            // Running promotion, if any. `ends_at` is a real fixed deadline:
            // when it passes the campaign stops applying and the price rises.
            'campaign' => $this->whenHas('campaign', fn ($value) => $value),
            'students_count' => $this->whenHas('students_count', fn ($value) => (int) $value),
            'rating' => $this->whenHas('rating', fn ($value) => $value === null ? null : round((float) $value, 1)),
            'reviews_count' => $this->whenHas('reviews_count', fn ($value) => (int) $value),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'is_enrolled' => $this->whenHas('is_enrolled', fn ($value) => (bool) $value),
            'full_access' => $this->whenHas('full_access', fn ($value) => (bool) $value),
        ];
    }
}
