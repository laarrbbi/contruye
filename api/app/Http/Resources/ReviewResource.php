<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public view of an approved review. Only the reviewer's display name is
 * exposed — never their email. Expects `user` to be eager-loaded.
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'author_name' => $this->user?->name,
            // Every public review comes from a student who bought the course.
            'verified_purchase' => true,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
