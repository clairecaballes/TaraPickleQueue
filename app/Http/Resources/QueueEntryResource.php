<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueEntryResource extends JsonResource
{
    /**
     * A queue row represents either a solo player (`user`) or a party (`group`),
     * never both. Nested relations are only rendered when eager loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,
            'court' => new CourtResource($this->whenLoaded('court')),
            'status' => $this->status->value,
            'position' => $this->position,
            'players_count' => $this->players_count,
            'user' => new UserResource($this->whenLoaded('user')),
            'group' => new GroupResource($this->whenLoaded('group')),
            'joined_at' => $this->joined_at?->toISOString(),
            'called_at' => $this->called_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
        ];
    }
}
