<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin representation of a course: raw fields in both languages.
 *
 * Counts appear when loaded with withCount()/loadCount(); nested modules
 * appear on the detail endpoint.
 */
class AdminCourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title_fr' => $this->title_fr,
            'title_ar' => $this->title_ar,
            'description_fr' => $this->description_fr,
            'description_ar' => $this->description_ar,
            'price_dzd' => $this->price_dzd,
            'price_eur' => $this->price_eur,
            'status' => $this->status,
            'cover_image' => $this->cover_image,
            'modules_count' => $this->whenCounted('modules'),
            'lessons_count' => $this->whenCounted('lessons'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'modules' => AdminModuleResource::collection($this->whenLoaded('modules')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
