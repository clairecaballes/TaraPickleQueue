<?php

namespace Tests\Feature;

use App\Enums\QueueStatus;
use App\Models\Court;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\VisitorSession;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = app(QueueService::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function player(float $skill = 3.0): User
    {
        return User::factory()->create(['skill_rating' => $skill]);
    }

    private function trackedSession(array $overrides = []): VisitorSession
    {
        return VisitorSession::create(array_merge([
            'visitor_id' => 'visitor-'.fake()->unique()->numberBetween(1, 9999),
            'session_id' => 'session-'.fake()->unique()->uuid(),
            'ip' => '127.0.0.1',
            'country' => 'Unknown',
            'hits' => 1,
            'started_at' => now(),
            'last_activity_at' => now(),
        ], $overrides));
    }

    /* ------------------------------------------------------------------ *
     * Visitor session tracking (TrackVisitor middleware)
     * ------------------------------------------------------------------ */

    public function test_page_load_records_a_visitor_session_and_cookies(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertDatabaseCount('visitor_sessions', 1);

        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());

        $this->assertTrue($cookies->has('tp_visitor'));
        $this->assertTrue($cookies->has('tp_session'));
    }

    public function test_active_session_increments_hits_for_the_same_visitor(): void
    {
        $this->get('/');

        $session = VisitorSession::firstOrFail();

        // withCookie() encrypts the value, so the EncryptCookies middleware
        // round-trips it back to the plain uuid the middleware stored.
        $this->withCookie('tp_visitor', $session->visitor_id)
            ->withCookie('tp_session', $session->session_id)
            ->get('/')
            ->assertOk();

        $this->assertDatabaseCount('visitor_sessions', 1);
        $this->assertDatabaseHas('visitor_sessions', ['id' => $session->id, 'hits' => 2]);
    }

    public function test_a_second_visitor_gets_their_own_session(): void
    {
        $this->get('/');
        $this->get('/');

        $this->assertDatabaseCount('visitor_sessions', 2);
        $this->assertSame(2, VisitorSession::distinct('visitor_id')->count('visitor_id'));
    }

    public function test_stale_session_is_closed_and_a_fresh_one_starts(): void
    {
        $this->trackedSession([
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-stale',
            'hits' => 3,
            'started_at' => now()->subMinutes(50),
            'last_activity_at' => now()->subMinutes(40),
        ]);

        $this->withCookie('tp_visitor', 'visitor-1')
            ->withCookie('tp_session', 'session-stale')
            ->get('/')
            ->assertOk();

        $this->assertDatabaseCount('visitor_sessions', 2);

        $closed = VisitorSession::where('session_id', 'session-stale')->first();

        $this->assertNotNull($closed->ended_at);

        $fresh = VisitorSession::where('session_id', '!=', 'session-stale')->first();

        $this->assertSame('visitor-1', $fresh->visitor_id);
        $this->assertSame(1, $fresh->hits);
    }

    /* ------------------------------------------------------------------ *
     * Admin analytics endpoint
     * ------------------------------------------------------------------ */

    public function test_analytics_endpoint_requires_admin(): void
    {
        Sanctum::actingAs($this->player());

        $this->getJson('/api/admin/analytics')->assertForbidden();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/analytics')->assertOk();
    }

    public function test_analytics_returns_visitor_and_session_aggregates(): void
    {
        // Returning visitor: one session before this month, one this month.
        $this->trackedSession([
            'visitor_id' => 'visitor-a',
            'country' => 'US',
            'region' => 'California',
            'started_at' => now()->subDays(40),
            'last_activity_at' => now()->subDays(40),
        ]);
        $this->trackedSession([
            'visitor_id' => 'visitor-a',
            'country' => 'US',
            'region' => 'California',
        ]);
        // Two more new visitors this month.
        $this->trackedSession([
            'visitor_id' => 'visitor-b',
            'country' => 'US',
            'region' => 'California',
        ]);
        $this->trackedSession([
            'visitor_id' => 'visitor-c',
            'country' => 'Philippines',
            'region' => 'Manila',
        ]);

        // A completed match so session stats have something to show.
        $court = Court::factory()->create();
        $players = collect([3.0, 3.1, 3.2, 3.3])->map(fn ($skill) => $this->player($skill));

        foreach ($players as $player) {
            $this->queue->join($court, $player);
        }

        $this->queue->callNextUp($court);
        $match = $this->queue->confirmCall($court);
        $this->queue->completeMatch($match, 11, 5);

        // A resolved queue entry with 30 minutes of dwell time.
        QueueEntry::factory()->create([
            'court_id' => $court->id,
            'user_id' => $this->player()->id,
            'status' => QueueStatus::Completed,
            'joined_at' => now()->subMinutes(40),
            'resolved_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/analytics')
            ->assertOk()
            ->assertJsonPath('overview.month_visitors', 3)
            ->assertJsonPath('overview.returning_this_month', 1)
            ->assertJsonPath('overview.new_this_month', 2)
            ->assertJsonPath('overview.month_sessions', 3)
            ->assertJsonCount(30, 'trends')
            ->assertJsonCount(24, 'peak_hours')
            ->assertJsonPath('matches.matches_played', 1)
            ->assertJsonPath('matches.matches_this_month', 1)
            ->assertJsonCount(24, 'matches.play_hours')
            ->assertJsonPath('queue.resolved_entries', 1)
            ->assertJsonPath('queue.queue_dwell_minutes', 30)
            ->assertJsonFragment(['label' => 'US · California', 'visitors' => 2])
            ->assertJsonFragment(['label' => 'Philippines · Manila', 'visitors' => 1]);
    }

    public function test_analytics_handles_empty_data(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/analytics')
            ->assertOk()
            ->assertJsonPath('overview.month_visitors', 0)
            ->assertJsonPath('matches.matches_played', 0)
            ->assertJsonCount(30, 'trends')
            ->assertJsonCount(24, 'peak_hours')
            ->assertJsonCount(24, 'matches.play_hours');
    }
}
