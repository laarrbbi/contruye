<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public, localized representation of a module.
 */
class ModuleResource extends JsonResource
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
            'price_dzd' => $this->price_dzd,
            'price_eur' => $this->price_eur,
            'is_owned' => $this->whenHas('is_owned', fn ($value) => (bool) $value),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
