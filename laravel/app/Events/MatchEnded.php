<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a match on a court is completed and the court becomes free again
 * (players are requeued per the court's rotation rule, and the next group may
 * already have been called).
 *
 * Channel: private court.{courtId}
 */
class MatchEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $courtId  Court that just became free.
     * @param  int  $matchId  The completed match.
     * @param  int|null  $winnerTeamId  Winning team id (null if not recorded).
     * @param  int  $scoreA  Final score of team A.
     * @param  int  $scoreB  Final score of team B.
     * @param  string  $endedAt  ISO-8601 completion timestamp.
     */
    public function __construct(
        public readonly int $courtId,
        public readonly int $matchId,
        public readonly ?int $winnerTeamId,
        public readonly int $scoreA,
        public readonly int $scoreB,
        public readonly string $endedAt,
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
            'match_id' => $this->matchId,
            'winner_team_id' => $this->winnerTeamId,
            'score_a' => $this->scoreA,
            'score_b' => $this->scoreB,
            'ended_at' => $this->endedAt,
        ];
    }
}
