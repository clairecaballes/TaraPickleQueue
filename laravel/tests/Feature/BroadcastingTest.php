<?php

namespace Tests\Feature;

use App\Enums\CourtRotationRule;
use App\Events\CourtCalled;
use App\Events\MatchEnded;
use App\Events\QueueUpdated;
use App\Models\Court;
use App\Models\Game;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Pusher\Pusher;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = app(QueueService::class);
    }

    private function court(): Court
    {
        return Court::factory()->create();
    }

    private function player(float $skill = 3.0): User
    {
        return User::factory()->create(['skill_rating' => $skill]);
    }

    private function playFour(Court $court, iterable $players): Game
    {
        foreach ($players as $player) {
            $this->queue->join($court, $player);
        }

        $called = $this->queue->callNextUp($court);
        $this->assertCount(4, $called);

        return $this->queue->confirmCall($court);
    }

    /*
    |--------------------------------------------------------------------------
    | Event dispatch
    |--------------------------------------------------------------------------
    */

    public function test_join_broadcasts_queue_updated(): void
    {
        Event::fake([QueueUpdated::class]);

        $court = $this->court();
        $user = $this->player();

        $this->queue->join($court, $user);

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($court, $user) {
            return $event->courtId === $court->id
                && $event->action === 'joined'
                && $event->counts['waiting'] === 1
                && $event->counts['called'] === 0
                && $event->entries[0]['players'][0]['id'] === $user->id;
        });
    }

    public function test_leave_broadcasts_queue_updated(): void
    {
        Event::fake([QueueUpdated::class]);

        $court = $this->court();
        $entry = $this->queue->join($court, $this->player());

        $this->queue->leave($entry);

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($court, $entry) {
            return $event->courtId === $court->id
                && $event->action === 'left'
                && $event->counts['waiting'] === 0
                && $event->entries[0]['id'] === $entry->id;
        });
    }

    public function test_call_next_up_broadcasts_court_called_when_court_fills(): void
    {
        Event::fake([QueueUpdated::class, CourtCalled::class]);

        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($skill) => $this->player($skill));

        foreach ($players as $player) {
            $this->queue->join($court, $player);
        }

        $this->queue->callNextUp($court);

        Event::assertDispatched(CourtCalled::class, function (CourtCalled $event) use ($court, $players) {
            $ids = collect($event->entries)
                ->flatMap(fn ($entry) => collect($entry['players'])->pluck('id'))
                ->sort()
                ->values()
                ->all();

            return $event->courtId === $court->id
                && count($event->entries) === 4
                && $ids === $players->pluck('id')->sort()->values()->all()
                && str_ends_with($event->expiresAt, 'Z');
        });

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($court) {
            return $event->courtId === $court->id
                && $event->action === 'called'
                && $event->counts['called'] === 4;
        });
    }

    public function test_call_next_up_skips_court_called_when_court_not_full(): void
    {
        Event::fake([CourtCalled::class]);

        $court = $this->court();
        foreach ([3.0, 3.1, 3.2] as $skill) {
            $this->queue->join($court, $this->player($skill));
        }

        $this->queue->callNextUp($court);

        Event::assertNotDispatched(CourtCalled::class);
    }

    public function test_confirm_call_broadcasts_queue_updated_confirmed(): void
    {
        Event::fake([QueueUpdated::class]);

        $court = $this->court();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($skill) => $this->player($skill));

        $this->playFour($court, $players);

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($court) {
            return $event->courtId === $court->id
                && $event->action === 'confirmed'
                && $event->counts['called'] === 0
                && $event->counts['on_court'] === 4
                && collect($event->entries)->every(fn ($entry) => $entry['status'] === 'on_court' && isset($entry['team_id']));
        });
    }

    public function test_complete_match_broadcasts_match_ended(): void
    {
        Event::fake([QueueUpdated::class, CourtCalled::class, MatchEnded::class]);

        $court = $this->court();
        $players = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($skill) => $this->player($skill));

        $match = $this->playFour($court, $players);

        $this->queue->completeMatch($match, 11, 5);

        Event::assertDispatched(MatchEnded::class, function (MatchEnded $event) use ($court, $match) {
            return $event->courtId === $court->id
                && $event->matchId === $match->id
                && $event->winnerTeamId === $match->teams[0]->id
                && $event->scoreA === 11
                && $event->scoreB === 5;
        });

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($court) {
            return $event->courtId === $court->id && $event->action === 'completed';
        });
    }

    public function test_complete_match_calls_and_broadcasts_court_called_for_next_group(): void
    {
        Event::fake([CourtCalled::class]);

        $court = Court::factory()->create(['rotation_rule' => CourtRotationRule::FourOnFourOff]);
        $first = collect([4.0, 3.9, 2.0, 1.9])->map(fn ($skill) => $this->player($skill));
        $second = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($skill) => $this->player($skill));

        $match = $this->playFour($court, $first);

        foreach ($second as $player) {
            $this->queue->join($court, $player);
        }

        $this->queue->completeMatch($match, 11, 5);

        Event::assertDispatched(CourtCalled::class, function (CourtCalled $event) use ($court, $second) {
            $ids = collect($event->entries)->map(
                fn ($entry) => collect($entry['players'])->pluck('id')->all()
            )->flatten()->sort()->values()->all();

            return $event->courtId === $court->id && $ids === $second->pluck('id')->sort()->values()->all();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Channel authorization
    |--------------------------------------------------------------------------
    |
    | Tests run with the "null" broadcast driver, whose auth() is a no-op — so
    | these tests swap in a PusherBroadcaster (the driver Reverb uses in
    | dev/prod) backed by a mocked Pusher client to exercise the real
    | authorization flow in routes/channels.php.
    */

    private function usePusherBroadcaster(): void
    {
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('authorizeChannel')->andReturn(json_encode([
            'auth' => 'fake:signature',
        ]));

        Broadcast::extend('pusher', fn () => new PusherBroadcaster($pusher));
        Broadcast::setDefaultDriver('pusher');

        // Channels were registered on the null broadcaster during boot, so
        // re-register the court channel on the pusher broadcaster.
        // NOTE: mirrors routes/channels.php — keep in sync if the rule changes.
        Broadcast::channel(
            'court.{courtId}',
            fn (User $user, int $courtId) => Court::whereKey($courtId)->exists()
        );
    }

    public function test_authenticated_user_can_authorize_court_channel(): void
    {
        $this->usePusherBroadcaster();

        $court = $this->court();
        Sanctum::actingAs($this->player());

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-court.{$court->id}",
            'socket_id' => '1234.5678',
        ])->assertOk();
    }

    public function test_channel_authorization_rejects_unknown_court(): void
    {
        $this->usePusherBroadcaster();

        Sanctum::actingAs($this->player());

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-court.9999',
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_channel_authorization_requires_authentication(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-court.1',
            'socket_id' => '1234.5678',
        ])->assertUnauthorized();
    }
}
