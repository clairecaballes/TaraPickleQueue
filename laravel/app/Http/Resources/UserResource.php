<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Sanitized user payload — never expose password, remember_token, etc.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'skill_rating' => $this->skill_rating !== null ? (float) $this->skill_rating : null,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'is_admin' => (bool) $this->is_admin,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
