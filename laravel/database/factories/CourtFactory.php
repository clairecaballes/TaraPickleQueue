<?php

namespace Database\Factories;

use App\Enums\CourtPlayType;
use App\Enums\CourtRotationRule;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Court '.fake()->unique()->numberBetween(1, 50),
            'location' => fake()->word(),
            'play_type' => CourtPlayType::Doubles,
            'max_players' => 4,
            'is_active' => true,
            'rotation_rule' => CourtRotationRule::WinnersStay,
            'skill_grouping' => false,
        ];
    }

    public function singles(): static
    {
        return $this->state(fn () => [
            'play_type' => CourtPlayType::Singles,
            'max_players' => 2,
        ]);
    }
}
