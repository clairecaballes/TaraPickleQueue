<?php

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Models\Court;
use App\Models\Game;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = app(QueueService::class);
    }

    private function court(array $overrides = []): Court
    {
        return Court::factory()->create($overrides);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function player(float $skill = 3.0): User
    {
        return User::factory()->create(['skill_rating' => $skill]);
    }

    /** Run the full flow for 4 solo players and return the match. */
    private function playFour(Court $court, iterable $players): Game
    {
        foreach ($players as $player) {
            $this->queue->join($court, $player);
        }

        $this->queue->callNextUp($court);

        return $this->queue->confirmCall($court);
    }

    /*
    |--------------------------------------------------------------------------
    | Courts overview
    |--------------------------------------------------------------------------
    */

    public function test_courts_index_returns_waiting_count_and_current_match(): void
    {
        Sanctum::actingAs($this->player());

        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);

        // A waiting player bumps the waiting_count.
        $this->queue->join($court, $this->player());

        $this->getJson('/api/courts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $court->id)
            ->assertJsonPath('data.0.waiting_count', 1)
            ->assertJsonPath('data.0.current_match.id', $match->id)
            ->assertJsonPath('data.0.current_match.status', 'ongoing')
            ->assertJsonCount(2, 'data.0.current_match.teams');
    }

    /*
    |--------------------------------------------------------------------------
    | User search
    |--------------------------------------------------------------------------
    */

    public function test_user_search_requires_admin(): void
    {
        Sanctum::actingAs($this->player());

        $this->getJson('/api/admin/users/search?q=ada')->assertForbidden();
    }

    public function test_admin_can_search_users_by_name_and_phone(): void
    {
        Sanctum::actingAs($this->admin());

        $user = User::factory()->create(['name' => 'Ada Lovelace', 'phone' => '+1 555 0100']);

        $this->getJson('/api/admin/users/search?q=lovelace')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id);

        $this->getJson('/api/admin/users/search?q=0100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Manual add / remove
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_add_player_to_queue(): void
    {
        Sanctum::actingAs($this->admin());

        $court = $this->court();
        $user = $this->player();

        $this->postJson("/api/admin/courts/{$court->id}/queue/add", [
            'user_id' => $user->id,
        ])->assertCreated();

        $this->assertDatabaseHas('queue_entries', [
            'court_id' => $court->id,
            'user_id' => $user->id,
            'status' => QueueStatus::Waiting->value,
        ]);
    }

    public function test_admin_add_player_rejects_missing_user(): void
    {
        Sanctum::actingAs($this->admin());

        $court = $this->court();

        $this->postJson("/api/admin/courts/{$court->id}/queue/add", [
            'user_id' => 9999,
        ])->assertUnprocessable();
    }

    public function test_admin_add_player_rejects_player_already_in_queue(): void
    {
        Sanctum::actingAs($this->admin());

        $court = $this->court();
        $user = $this->player();

        $this->postJson("/api/admin/courts/{$court->id}/queue/add", ['user_id' => $user->id])->assertCreated();
        $this->postJson("/api/admin/courts/{$court->id}/queue/add", ['user_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('queue');
    }

    /*
    |--------------------------------------------------------------------------
    | Reorder
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_reorder_the_waiting_line(): void
    {
        Sanctum::actingAs($this->admin());

        $court = $this->court();
        $entries = [];

        foreach ([3.0, 3.1, 3.2, 3.3] as $skill) {
            $entries[] = $this->queue->join($court, $this->player($skill));
        }

        $reversed = collect($entries)->pluck('id')->reverse()->values()->all();

        $this->patchJson("/api/admin/courts/{$court->id}/queue/reorder", [
            'ordered_ids' => $reversed,
        ])->assertOk();

        $positions = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $this->assertSame($reversed, $positions);
    }

    public function test_reorder_rejects_incomplete_list(): void
    {
        Sanctum::actingAs($this->admin());

        $court = $this->court();
        $entries = [];

        foreach ([3.0, 3.1, 3.2] as $skill) {
            $entries[] = $this->queue->join($court, $this->player($skill));
        }

        // Only two of the three waiting entries provided.
        $this->patchJson("/api/admin/courts/{$court->id}/queue/reorder", [
            'ordered_ids' => [$entries[0]->id, $entries[1]->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ordered_ids');
    }

    /*
    |--------------------------------------------------------------------------
    | Gated admin actions
    |--------------------------------------------------------------------------
    */

    public function test_non_admin_cannot_call_next_up(): void
    {
        Sanctum::actingAs($this->player());

        $this->postJson('/api/courts/'.$this->court()->id.'/next-up')->assertForbidden();
    }

    public function test_non_admin_cannot_complete_match(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $match = $this->playFour($court, $players);

        Sanctum::actingAs($this->player());

        $this->postJson("/api/matches/{$match->id}/complete", [
            'score_a' => 11,
            'score_b' => 5,
        ])->assertForbidden();
    }

    public function test_admin_can_complete_match_via_api(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $match = $this->playFour($court, $players);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/matches/{$match->id}/complete", [
            'score_a' => 11,
            'score_b' => 5,
        ])->assertOk()->assertJsonPath('match.status', 'completed');
    }
}
