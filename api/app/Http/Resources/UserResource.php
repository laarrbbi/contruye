<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'locale' => $this->locale,
            // Where the learner is building: partner lists default to it.
            'province' => $this->whenLoaded('province', fn () => [
                'id' => $this->province->id,
                'code' => $this->province->code,
                'name' => $this->province->localizedName(),
            ]),
            'province_id' => $this->province_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
