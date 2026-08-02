<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court' => new CourtResource($this->whenLoaded('court')),
            'status' => $this->status->value,
            'winner_team_id' => $this->winner_team_id,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
        ];
    }
}
