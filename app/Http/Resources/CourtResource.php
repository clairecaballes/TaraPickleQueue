<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'play_type' => $this->play_type->value,
            'max_players' => $this->max_players,
            'is_active' => $this->is_active,
            // Populated when the court is loaded with ->withCount('queueEntries').
            'queue_length' => $this->whenCounted('queue_entries_count'),
        ];
    }
}
