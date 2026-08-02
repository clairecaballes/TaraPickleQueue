<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every queue mutation for a court: a player/group joins, leaves,
 * is skipped, moves position, is called to court, or a finished match requeues
 * players.
 *
 * Bandwidth is kept minimal: the payload only carries the compact final state
 * (three counts + the waiting line as [id, position] pairs) plus the affected
 * entries. Clients can render directly from the payload without a REST re-fetch.
 *
 * Channel: private court.{courtId}
 */
class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $courtId  Court whose queue changed.
     * @param  string  $action  joined|left|skipped|called|confirmed|completed|expired.
     * @param  array{waiting: int, called: int, on_court: int}  $counts  Final queue counts.
     * @param  array<int, array{0: int, 1: int}>  $positions  Waiting line as [entry_id, position] pairs.
     * @param  array<int, array<string, mixed>>  $entries  Compact affected entries (join/leave/skip/confirm).
     */
    public function __construct(
        public readonly int $courtId,
        public readonly string $action,
        public readonly array $counts,
        public readonly array $positions,
        public readonly array $entries = [],
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
        $payload = [
            'court_id' => $this->courtId,
            'action' => $this->action,
            'waiting_count' => $this->counts['waiting'] ?? 0,
            'called_count' => $this->counts['called'] ?? 0,
            'on_court_count' => $this->counts['on_court'] ?? 0,
            'positions' => $this->positions,
        ];

        if ($this->entries !== []) {
            $payload['entries'] = $this->entries;
        }

        return $payload;
    }
}
