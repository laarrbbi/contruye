<?php

namespace App\Http\Resources;

use App\Support\ContentValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin representation of a module: raw fields in both languages.
 */
class AdminModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'order' => $this->order,
            'title_fr' => $this->title_fr,
            'title_ar' => $this->title_ar,
            'description_fr' => $this->description_fr,
            // Admin-only editorial signal: this passage still needs a notary /
            // jurist sign-off. Never sent to students.
            'description_needs_validation' => ContentValidation::flags($this->description_fr),
            'blocks' => $this->whenLoaded('contentBlocks', fn () => $this->contentBlocks
                ->map(fn ($block) => [
                    'id' => $block->id,
                    'type' => $block->type,
                    'body_fr' => $block->body_fr,
                    'needs_validation' => $block->needs_validation,
                ])->values()),
            'price_dzd' => $this->price_dzd,
            'price_eur' => $this->price_eur,
            'partners' => $this->whenLoaded('partners', fn () => $this->partners
                ->map(fn ($partner) => [
                    'partner_id' => $partner->id,
                    'name' => $partner->name,
                    'category' => $partner->category,
                    'order' => (int) $partner->pivot->order,
                    'note_fr' => $partner->pivot->note_fr,
                    'note_ar' => $partner->pivot->note_ar,
                ])->values()),
            'lessons' => AdminLessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
