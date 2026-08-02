<?php

namespace Tests\Feature;

use App\Enums\CourtRotationRule;
use App\Enums\MatchStatus;
use App\Enums\QueueStatus;
use App\Models\Court;
use App\Models\Game;
use App\Models\Group;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueueEngineTest extends TestCase
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

    private function player(float $skill): User
    {
        return User::factory()->create(['skill_rating' => $skill]);
    }

    private function join(User $user, Court $court, ?Group $group = null): QueueEntry
    {
        return $this->queue->join($court, $user, $group);
    }

    /** Run the full flow for 4 solo players: call + confirm, returns the match. */
    private function playFour(Court $court, iterable $players): Game
    {
        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);
        $this->assertCount(4, $called);

        return $this->queue->confirmCall($court);
    }

    /** Sorted ids of the users on team A / team B of the match. */
    private function teamUserIds(Game $match, int $teamIndex): array
    {
        return $match->teams[$teamIndex]->users->pluck('id')->sort()->values()->all();
    }

    private function calledUserIds(Court $court): array
    {
        return QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Called)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Joining
    |--------------------------------------------------------------------------
    */

    public function test_solo_player_can_join_queue(): void
    {
        $court = $this->court();
        $user = $this->player(3.5);

        $entry = $this->join($user, $court);

        $this->assertSame(QueueStatus::Waiting, $entry->status);
        $this->assertSame(0, $entry->position);
        $this->assertSame(1, $entry->players_count);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertDatabaseHas('queue_entries', ['id' => $entry->id, 'status' => 'waiting']);
    }

    public function test_group_can_join_queue_as_unit(): void
    {
        $court = $this->court();
        $leader = $this->player(3.0);
        $mate = $this->player(3.2);
        $group = Group::factory()->create(['created_by_user_id' => $leader->id]);
        $group->users()->attach([$leader->id, $mate->id]);

        $entry = $this->join($leader, $court, $group);

        $this->assertSame($group->id, $entry->group_id);
        $this->assertNull($entry->user_id);
        $this->assertSame(2, $entry->players_count);
    }

    public function test_group_of_four_fills_doubles_court(): void
    {
        $court = $this->court();
        $members = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $group = Group::factory()->create();
        $group->users()->attach($members->pluck('id'));

        $entry = $this->join($members->first(), $court, $group);

        $this->assertSame(4, $entry->players_count);

        $called = $this->queue->callNextUp($court);
        $this->assertCount(1, $called);
        $this->assertSame($entry->id, $called[0]->id);
    }

    public function test_user_cannot_join_when_already_active_on_another_court(): void
    {
        $courtA = $this->court();
        $courtB = $this->court();
        $user = $this->player(3.0);

        $this->join($user, $courtA);

        $this->expectExceptionMessage('already in a queue or on a court');
        $this->join($user, $courtB);
    }

    public function test_user_cannot_join_same_court_twice(): void
    {
        $court = $this->court();
        $user = $this->player(3.0);

        $this->join($user, $court);

        $this->expectExceptionMessage('already in a queue or on a court');
        $this->join($user, $court);
    }

    public function test_group_member_active_elsewhere_blocks_the_whole_group(): void
    {
        $courtA = $this->court();
        $courtB = $this->court();
        $leader = $this->player(3.0);
        $mate = $this->player(3.2);

        $this->join($mate, $courtA);

        $group = Group::factory()->create();
        $group->users()->attach([$leader->id, $mate->id]);

        $this->expectExceptionMessage('already in a queue or on a court');
        $this->join($leader, $courtB, $group);
    }

    public function test_non_member_cannot_join_with_group(): void
    {
        $court = $this->court();
        $leader = $this->player(3.0);
        $mate = $this->player(3.2);
        $outsider = $this->player(3.0);

        $group = Group::factory()->create();
        $group->users()->attach([$leader->id, $mate->id]);

        $this->expectExceptionMessage('not a member');
        $this->join($outsider, $court, $group);
    }

    public function test_group_too_large_for_court_is_rejected(): void
    {
        $court = $this->court(['max_players' => 2]); // singles court
        $members = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $group = Group::factory()->create();
        $group->users()->attach($members->pluck('id'));

        $this->expectExceptionMessage('too large');
        $this->join($members->first(), $court, $group);
    }

    public function test_leave_removes_waiting_entry(): void
    {
        $court = $this->court();
        $user = $this->player(3.0);

        $entry = $this->join($user, $court);
        $this->queue->leave($entry);

        $this->assertDatabaseMissing('queue_entries', ['id' => $entry->id]);
    }

    public function test_join_via_api_returns_entry(): void
    {
        $court = $this->court();
        $user = $this->player(3.0);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/courts/{$court->id}/queue");

        $response->assertCreated()->assertJsonPath('queue_entry.user.id', $user->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Calling next-up
    |--------------------------------------------------------------------------
    */

    public function test_call_next_up_calls_first_four_fifo(): void
    {
        $court = $this->court();
        $players = collect([4.0, 2.0, 5.0, 1.0, 3.0])->map(fn ($s) => $this->player($s));

        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);

        $this->assertCount(4, $called);
        $calledUserIds = collect($called)->map(fn ($e) => $e->user_id)->all();
        $this->assertSame($players->take(4)->pluck('id')->all(), $calledUserIds);

        $this->assertDatabaseHas('queue_entries', ['user_id' => $players[4]->id, 'status' => 'waiting']);
        foreach ($called as $entry) {
            $this->assertSame(QueueStatus::Called, $entry->status);
            $this->assertNull($entry->position);
            $this->assertNotNull($entry->called_at);
        }
    }

    public function test_call_next_up_does_not_call_when_court_cannot_be_filled(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2])->map(fn ($s) => $this->player($s));

        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);

        $this->assertSame([], $called);
        $this->assertDatabaseCount('queue_entries', 3);
    }

    public function test_skill_grouping_calls_similar_skill_players_first(): void
    {
        $court = $this->court(['skill_grouping' => true]);
        // Joined in this order: anchor 4.0, then 4.2, 3.8, 1.0, 4.1, 5.0
        $anchor = $this->player(4.0);
        $players = collect([4.2, 3.8, 1.0, 4.1, 5.0])->map(fn ($s) => $this->player($s));

        $this->join($anchor, $court);
        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);

        $calledUserIds = collect($called)->map(fn ($e) => $e->user_id)->all();

        // The 1.0 and 5.0 outliers stay waiting; the 4.0 skill cluster is called.
        $this->assertNotContains($players[2]->id, $calledUserIds); // 1.0
        $this->assertNotContains($players[4]->id, $calledUserIds); // 5.0
        $this->assertContains($anchor->id, $calledUserIds);
        $this->assertCount(4, $called);
    }

    /*
    |--------------------------------------------------------------------------
    | Confirming a call / starting a match
    |--------------------------------------------------------------------------
    */

    public function test_confirm_call_creates_match_with_two_teams(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);

        $this->assertSame(MatchStatus::Ongoing, $match->status);
        $this->assertNotNull($match->started_at);
        $this->assertCount(2, $match->teams);

        foreach ($match->teams as $team) {
            $this->assertCount(2, $team->users);
        }

        $this->assertDatabaseCount('queue_entries', 4);
        $this->assertSame(
            4,
            QueueEntry::where('court_id', $court->id)->where('status', QueueStatus::OnCourt)->count(),
        );
        $this->assertDatabaseHas('team_user', ['team_id' => $match->teams[0]->id]);
        $this->assertDatabaseHas('team_user', ['team_id' => $match->teams[1]->id]);
    }

    public function test_confirm_call_requires_full_court(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2])->map(fn ($s) => $this->player($s));

        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);
        $this->assertSame([], $called);

        $this->expectExceptionMessage('Court is not full');
        $this->queue->confirmCall($court);
    }

    public function test_two_groups_keep_their_teams_intact(): void
    {
        $court = $this->court();
        $leaderA = $this->player(3.0);
        $mateA = $this->player(3.2);
        $leaderB = $this->player(3.1);
        $mateB = $this->player(3.3);

        $groupA = Group::factory()->create();
        $groupA->users()->attach([$leaderA->id, $mateA->id]);
        $groupB = Group::factory()->create();
        $groupB->users()->attach([$leaderB->id, $mateB->id]);

        $this->join($leaderA, $court, $groupA);
        $this->join($leaderB, $court, $groupB);

        $called = $this->queue->callNextUp($court);
        $this->assertCount(2, $called);

        $match = $this->queue->confirmCall($court);

        $teamPlayerSets = $match->teams->map(
            fn ($team) => $team->users->pluck('id')->sort()->values()->all()
        );

        // Each team is exactly one of the two groups.
        $this->assertContains([$leaderA->id, $mateA->id], $teamPlayerSets->all());
        $this->assertContains([$leaderB->id, $mateB->id], $teamPlayerSets->all());
    }

    public function test_group_of_four_is_split_but_rotates_as_a_unit(): void
    {
        $court = $this->court(['rotation_rule' => CourtRotationRule::WinnersStay]);
        $members = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $group = Group::factory()->create();
        $group->users()->attach($members->pluck('id'));

        $entry = $this->join($members->first(), $court, $group);
        $this->assertSame(4, $entry->players_count);

        $called = $this->queue->callNextUp($court);
        $this->assertCount(1, $called);

        $match = $this->queue->confirmCall($court);

        // Split into two teams for the match...
        $this->assertCount(2, $match->teams);
        foreach ($match->teams as $team) {
            $this->assertCount(2, $team->users);
        }

        $this->queue->completeMatch($match, 11, 5);

        // ...but rotates as a single queue unit: winners_stay keeps it next up.
        $this->assertSame(
            [$entry->id],
            QueueEntry::where('court_id', $court->id)
                ->where('status', QueueStatus::Called)
                ->pluck('id')
                ->all(),
        );

        // Stats are still recorded per player.
        $this->assertSame(2, $group->users()->where('wins', 1)->count());
        $this->assertSame(2, $group->users()->where('losses', 1)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Completing a match + rotation rules
    |--------------------------------------------------------------------------
    */

    public function test_complete_match_winners_stay_requeues_winners_front(): void
    {
        $court = $this->court(['rotation_rule' => CourtRotationRule::WinnersStay]);
        $players = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($s) => $this->player($s));
        $nextTwo = collect([3.0, 3.1])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);
        $winnerUserIds = $this->teamUserIds($match, 0); // Team A wins (score_a = 11)
        $loserUserIds = $this->teamUserIds($match, 1);

        foreach ($nextTwo as $player) {
            $this->join($player, $court);
        }

        $this->queue->completeMatch($match, 11, 5);

        $this->assertSame(MatchStatus::Completed, $match->fresh()->status);
        $this->assertNotNull($match->fresh()->ended_at);

        // Winners stay: they (with the next two) are called; losers are left waiting.
        $this->assertSame(
            collect([...$winnerUserIds, ...$nextTwo->pluck('id')->all()])->sort()->values()->all(),
            $this->calledUserIds($court),
        );

        $waiting = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->orderBy('position')
            ->get();

        $this->assertSame($loserUserIds, $waiting->pluck('user_id')->sort()->values()->all());
        $this->assertSame([0, 1], $waiting->pluck('position')->all());
    }

    public function test_complete_match_four_on_four_off_requeues_everyone_at_back(): void
    {
        $court = $this->court(['rotation_rule' => CourtRotationRule::FourOnFourOff]);
        $players = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($s) => $this->player($s));
        $late = collect([3.0, 3.1])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);
        $winnerUserIds = $this->teamUserIds($match, 0); // Team A rotates off first
        $loserUserIds = $this->teamUserIds($match, 1);

        foreach ($late as $player) {
            $this->join($player, $court);
        }

        $this->queue->completeMatch($match, 11, 5);

        // Everyone rotated off: the late joiners are ahead, then the winner team.
        $this->assertSame(
            collect([...$late->pluck('id')->all(), ...$winnerUserIds])->sort()->values()->all(),
            $this->calledUserIds($court),
        );

        $waitingUserIds = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($loserUserIds, $waitingUserIds);
    }

    public function test_complete_match_losers_out_completes_losers(): void
    {
        $court = $this->court(['rotation_rule' => CourtRotationRule::LosersOut]);
        $players = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);
        $winnerUserIds = $this->teamUserIds($match, 0);
        $loserUserIds = $this->teamUserIds($match, 1);

        $this->queue->completeMatch($match, 11, 5);

        $this->assertSame(
            2,
            QueueEntry::whereIn('user_id', $loserUserIds)->where('status', QueueStatus::Completed)->count(),
        );

        $waitingUserIds = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($winnerUserIds, $waitingUserIds);
    }

    public function test_complete_match_updates_player_stats(): void
    {
        $court = $this->court();
        $players = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);
        $winnerUserIds = $this->teamUserIds($match, 0);
        $loserUserIds = $this->teamUserIds($match, 1);

        $this->queue->completeMatch($match, 11, 5);

        foreach ($winnerUserIds as $id) {
            $this->assertSame(1, User::find($id)->wins);
        }
        foreach ($loserUserIds as $id) {
            $this->assertSame(1, User::find($id)->losses);
        }
    }

    public function test_complete_match_rejects_tied_scores(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $players);

        $this->expectExceptionMessage('Scores cannot be tied');
        $this->queue->completeMatch($match, 11, 11);
    }

    public function test_completed_match_immediately_calls_next_group(): void
    {
        $court = $this->court(['rotation_rule' => CourtRotationRule::FourOnFourOff]);
        $first = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($s) => $this->player($s));
        $second = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));

        $match = $this->playFour($court, $first);
        foreach ($second as $player) {
            $this->join($player, $court);
        }

        $this->queue->completeMatch($match, 11, 5);

        $this->assertSame(
            $second->pluck('id')->sort()->all(),
            $this->calledUserIds($court),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Skipping / timeouts
    |--------------------------------------------------------------------------
    */

    public function test_manual_skip_moves_next_player_up(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3, 3.4])->map(fn ($s) => $this->player($s));

        $entries = [];
        foreach ($players as $player) {
            $entries[] = $this->join($player, $court);
        }

        $this->queue->skip($entries[0]);

        $this->assertSame(QueueStatus::Skipped, $entries[0]->fresh()->status);

        // The remaining four fill the court and are called.
        $this->assertSame($players->slice(1)->pluck('id')->sort()->values()->all(), $this->calledUserIds($court));
    }

    public function test_leaving_a_called_entry_refills_the_court(): void
    {
        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3, 3.4])->map(fn ($s) => $this->player($s));

        foreach ($players as $player) {
            $this->join($player, $court);
        }

        $called = $this->queue->callNextUp($court);
        $this->assertCount(4, $called);

        // The first called player leaves before confirming...
        $this->queue->leave($called[0]);

        // ...and the next waiting player is called in their place.
        $this->assertSame(
            $players->slice(1)->pluck('id')->sort()->values()->all(),
            QueueEntry::where('court_id', $court->id)
                ->where('status', QueueStatus::Called)
                ->pluck('user_id')
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_skip_expired_calls_skips_and_refills(): void
    {
        $court = $this->court();
        $firstFour = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($s) => $this->player($s));
        $backup = collect([3.4, 3.5, 3.6, 3.7])->map(fn ($s) => $this->player($s));

        foreach ($firstFour as $player) {
            $this->join($player, $court);
        }
        $called = $this->queue->callNextUp($court);
        $this->assertCount(4, $called);

        // Age the calls beyond the 2-minute window.
        QueueEntry::where('status', QueueStatus::Called)->update([
            'called_at' => now()->subMinutes(QueueService::CALL_TIMEOUT_MINUTES + 1),
        ]);

        foreach ($backup as $player) {
            $this->join($player, $court);
        }

        $skipped = $this->queue->skipExpiredCalls();

        $this->assertSame(4, $skipped);
        $this->assertSame(
            4,
            QueueEntry::where('court_id', $court->id)->where('status', QueueStatus::Skipped)->count(),
        );

        // The court is refilled with the backup players.
        $this->assertSame($backup->pluck('id')->sort()->all(), $this->calledUserIds($court));
    }

    public function test_skip_via_api_requires_admin(): void
    {
        $court = $this->court();
        $admin = $this->player(3.0);
        $admin->forceFill(['is_admin' => true])->save();
        $user = $this->player(3.0);

        $entry = $this->join($user, $court);

        // Non-admins are rejected by the can:manage-court middleware.
        Sanctum::actingAs($this->player(3.0));
        $this->postJson("/api/queue/{$entry->id}/skip")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson("/api/queue/{$entry->id}/skip")->assertOk();

        $this->assertSame(QueueStatus::Skipped, $entry->fresh()->status);
    }
}
