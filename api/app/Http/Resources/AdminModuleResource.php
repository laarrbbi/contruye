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
            'title_es' => $this->title_es,
            'title_en' => $this->title_en,
            'description_es' => $this->description_es,
            // Admin-only editorial signal: this passage still needs a notary /
            // jurist sign-off. Never sent to students.
            'description_needs_validation' => ContentValidation::flags($this->description_es),
            'blocks' => $this->whenLoaded('contentBlocks', fn () => $this->contentBlocks
                ->map(fn ($block) => [
                    'id' => $block->id,
                    'type' => $block->type,
                    'body_es' => $block->body_es,
                    'needs_validation' => $block->needs_validation,
                ])->values()),
            'price_eur' => $this->price_eur,
            'partners' => $this->whenLoaded('partners', fn () => $this->partners
                ->map(fn ($partner) => [
                    'partner_id' => $partner->id,
                    'name' => $partner->name,
                    'category' => $partner->category,
                    'order' => (int) $partner->pivot->order,
                    'note_es' => $partner->pivot->note_es,
                    'note_en' => $partner->pivot->note_en,
                ])->values()),
            'lessons' => AdminLessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
