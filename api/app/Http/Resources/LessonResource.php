<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public, localized representation of a lesson (no video id leaked).
 */
class LessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'title' => $this->localizedTitle(),
            'duration_seconds' => $this->duration_seconds,
            'is_free_preview' => $this->is_free_preview,
        ];
    }
}
