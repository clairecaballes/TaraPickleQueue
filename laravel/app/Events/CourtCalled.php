<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the court's queue fills and the next batch of players (typically
 * 4 on a doubles court) is summoned to the court.
 *
 * Payload carries the compact called entries plus the confirmation deadline
 * (expires_at) so clients can render a countdown — the call auto-expires after
 * CALL_TIMEOUT_MINUTES via `php artisan queue:skip-expired-calls`.
 *
 * Channel: private court.{courtId}
 */
class CourtCalled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $courtId  Court the players were called to.
     * @param  array<int, array<string, mixed>>  $entries  Compact called queue entries (full batch).
     * @param  string  $expiresAt  ISO-8601 deadline for the players to confirm.
     */
    public function __construct(
        public readonly int $courtId,
        public readonly array $entries,
        public readonly string $expiresAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("court.{$this->courtId}")];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'court_id' => $this->courtId,
            'entries' => $this->entries,
            'expires_at' => $this->expiresAt,
        ];
    }
}
