<?php

namespace App\Http\Resources;

use App\Support\ContentValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin representation of a lesson: all raw fields.
 */
class AdminLessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'order' => $this->order,
            'title_fr' => $this->title_fr,
            'title_ar' => $this->title_ar,
            'bunny_video_id' => $this->bunny_video_id,
            'duration_seconds' => $this->duration_seconds,
            'is_free_preview' => $this->is_free_preview,
            'content_fr' => $this->content_fr,
            'content_ar' => $this->content_ar,
            'is_reflex_sheet' => (bool) $this->is_reflex_sheet,
            // Each bullet carries its own admin-only "A VALIDER" state.
            'key_points' => ContentValidation::annotate($this->key_points_fr ?? []),
            'attachments' => $this->attachments,
        ];
    }
}
