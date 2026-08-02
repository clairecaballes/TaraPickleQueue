<?php

namespace Database\Seeders;

use App\Enums\CourtPlayType;
use App\Enums\CourtRotationRule;
use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Organizer account — can manage courts (can:manage-court gate).
        $admin = User::factory()->create([
            'name' => 'Court Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        Court::create([
            'name' => 'Main Court',
            'location' => 'Indoor Hall',
            'play_type' => CourtPlayType::Doubles,
            'max_players' => 4,
            'is_active' => true,
            'rotation_rule' => CourtRotationRule::WinnersStay,
            'skill_grouping' => false,
        ]);

        Court::create([
            'name' => 'Side Court',
            'location' => 'Indoor Hall',
            'play_type' => CourtPlayType::Singles,
            'max_players' => 2,
            'is_active' => true,
            'rotation_rule' => CourtRotationRule::WinnersStay,
            'skill_grouping' => false,
        ]);
    }
}
