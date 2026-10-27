<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Progress state of a single lesson for the authenticated user,
 * returned by the heartbeat and complete endpoints.
 */
class LessonProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lesson_id' => $this->lesson_id,
            'last_position_seconds' => (int) $this->last_position_seconds,
            'is_completed' => $this->completed_at !== null,
        ];
    }
}
