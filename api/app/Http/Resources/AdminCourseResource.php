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
            'title_es' => $this->title_es,
            'title_en' => $this->title_en,
            'description_es' => $this->description_es,
            'description_en' => $this->description_en,
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
