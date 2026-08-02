<?php

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\Court;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'user_id' => null,
            'group_id' => null,
            'team_id' => null,
            'players_count' => 1,
            'position' => null,
            'status' => QueueStatus::Waiting,
            'joined_at' => now(),
            'called_at' => null,
            'resolved_at' => null,
        ];
    }
}
