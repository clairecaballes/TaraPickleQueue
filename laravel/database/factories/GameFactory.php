<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Court;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'status' => MatchStatus::Ongoing,
            'winner_team_id' => null,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
