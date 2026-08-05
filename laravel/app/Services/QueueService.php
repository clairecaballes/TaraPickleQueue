<?php

namespace App\Services;

use App\Enums\CourtRotationRule;
use App\Enums\MatchStatus;
use App\Enums\QueueStatus;
use App\Events\CourtCalled;
use App\Events\MatchEnded;
use App\Events\QueueUpdated;
use App\Models\Court;
use App\Models\Game;
use App\Models\Group;
use App\Models\QueueEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Core queue engine for Pickle Ta Bai!.
 *
 * Every operation runs inside a DB transaction and uses pessimistic row locks
 * (lockForUpdate) so concurrent joins/calls cannot double-book a player or
 * assign the same queue position twice.
 *
 * Lifecycle of a queue entry:
 *   waiting -> called (2 min window) -> on_court -> completed | skipped
 */
class QueueService
{
    /** How long a called unit has to confirm before being auto-skipped. */
    public const CALL_TIMEOUT_MINUTES = 2;

    /** Offset used to move waiting entries out of the way before requeueing. */
    private const POSITION_SHIFT = 100000;

    /**
     * Join a court's queue as a solo player or as part of a group.
     *
     * @throws ValidationException
     */
    public function join(Court $court, User $user, ?Group $group = null): QueueEntry
    {
        $entry = DB::transaction(function () use ($court, $user, $group) {
            $court = Court::whereKey($court->id)->lockForUpdate()->firstOrFail();

            if (! $court->is_active) {
                throw ValidationException::withMessages([
                    'court' => ['This court is not active.'],
                ]);
            }

            $memberIds = $group ? $group->users()->pluck('users.id')->all() : [$user->id];

            if ($group) {
                if (! in_array($user->id, $memberIds, true)) {
                    throw ValidationException::withMessages([
                        'group_id' => ['You are not a member of this group.'],
                    ]);
                }

                $size = count($memberIds);
                if (! in_array($size, [2, 4], true)) {
                    throw ValidationException::withMessages([
                        'group_id' => ['A group must have exactly 2 or 4 players.'],
                    ]);
                }

                if ($size > $court->max_players) {
                    throw ValidationException::withMessages([
                        'group_id' => ['This group is too large for this court.'],
                    ]);
                }
            }

            // Lock every participant's user row so two concurrent joins by the
            // same player (or on different courts) cannot both succeed.
            User::whereKey($memberIds)->lockForUpdate()->get();

            $alreadyActive = QueueEntry::whereIn('user_id', $memberIds)
                ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::OnCourt])
                ->exists();

            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'queue' => ['One or more players are already in a queue or on a court.'],
                ]);
            }

            $position = (QueueEntry::where('court_id', $court->id)
                ->where('status', QueueStatus::Waiting)
                ->max('position') ?? -1) + 1;

            return QueueEntry::create([
                'court_id' => $court->id,
                'user_id' => $group ? null : $user->id,
                'group_id' => $group?->id,
                'players_count' => $group ? count($memberIds) : 1,
                'position' => $position,
                'status' => QueueStatus::Waiting,
            ]);
        });

        // Broadcast after the transaction commits so listeners never see a phantom state.
        $this->broadcastQueueChanged($court, 'joined', entries: [$this->compactEntry($entry)]);

        return $entry;
    }

    /**
     * Leave the queue. Only allowed while the entry is still waiting or called.
     */
    public function leave(QueueEntry $entry): void
    {
        $result = DB::transaction(function () use ($entry) {
            $entry = QueueEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status === QueueStatus::OnCourt) {
                throw ValidationException::withMessages([
                    'queue' => ['Cannot leave while on court.'],
                ]);
            }

            $court = $entry->court;
            $entry->delete();

            // Refills the court when a pending call loses a member (and keeps
            // the remaining waiting line compact via the internal renumber).
            $calledBefore = $this->calledCount($court);
            $called = $this->fillCourt($court);

            return compact('court', 'called', 'calledBefore');
        });

        $this->broadcastQueueChanged($result['court'], 'left', entries: [$this->compactEntry($entry)]);
        $this->broadcastCourtCalledIfNew($result['court'], $result['called'], $result['calledBefore']);
    }

    /**
     * Manually skip a waiting/called entry (admin action).
     */
    public function skip(QueueEntry $entry): void
    {
        $result = DB::transaction(function () use ($entry) {
            $entry = QueueEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! in_array($entry->status, [QueueStatus::Waiting, QueueStatus::Called], true)) {
                throw ValidationException::withMessages([
                    'queue' => ['Only waiting or called entries can be skipped.'],
                ]);
            }

            $court = $entry->court;
            $entry->update([
                'status' => QueueStatus::Skipped,
                'position' => null,
                'resolved_at' => now(),
            ]);

            $calledBefore = $this->calledCount($court);
            $called = $this->fillCourt($court);

            return compact('court', 'called', 'calledBefore');
        });

        $this->broadcastQueueChanged($result['court'], 'skipped', entries: [$this->compactEntry($entry)]);
        $this->broadcastCourtCalledIfNew($result['court'], $result['called'], $result['calledBefore']);
    }

    /**
     * Call the next units up to court capacity (FIFO or skill-grouped) and move
     * them to the "called" status. Returns the batch of called entries.
     *
     * @return QueueEntry[]
     */
    public function callNextUp(Court $court): array
    {
        $result = DB::transaction(function () use ($court) {
            $calledBefore = $this->calledCount($court);
            $called = $this->fillCourt($court);

            return compact('called', 'calledBefore');
        });

        $this->broadcastQueueChanged($court, 'called');
        $this->broadcastCourtCalledIfNew($court, $result['called'], $result['calledBefore']);

        return $result['called'];
    }

    /**
     * Admin override: reassign the waiting line positions from an explicit
     * ordering. ordered_ids must contain exactly the court's waiting entries
     * (the full line, top to bottom) — any missing/foreign id is rejected.
     *
     * @param  array<int, int>  $orderedIds
     *
     * @throws ValidationException
     */
    public function reorder(Court $court, array $orderedIds): void
    {
        $result = DB::transaction(function () use ($court, $orderedIds) {
            $court = Court::whereKey($court->id)->lockForUpdate()->firstOrFail();

            $waitingIds = QueueEntry::where('court_id', $court->id)
                ->where('status', QueueStatus::Waiting)
                ->lockForUpdate()
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $expected = collect($orderedIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($waitingIds !== $expected) {
                throw ValidationException::withMessages([
                    'ordered_ids' => ['The list must contain exactly the waiting entries of this court.'],
                ]);
            }

            // Assign the new order at an offset first so the UNIQUE
            // (court_id, position) index never collides mid-update, then
            // compact back to 0..n-1 in the new order.
            foreach (array_values($orderedIds) as $index => $id) {
                QueueEntry::whereKey((int) $id)->update(['position' => self::POSITION_SHIFT + $index]);
            }

            $this->renumber($court);
        });

        $this->broadcastQueueChanged($court, 'moved');
    }

    /**
     * Confirm a pending call: move the called batch to the court, create the
     * match (recording match started_at) and split players into two teams.
     */
    public function confirmCall(Court $court): Game
    {
        $result = DB::transaction(function () use ($court) {
            $court = Court::whereKey($court->id)->lockForUpdate()->firstOrFail();

            $called = QueueEntry::where('court_id', $court->id)
                ->where('status', QueueStatus::Called)
                ->with(['user', 'group.users'])
                ->lockForUpdate()
                ->get()
                ->values();

            $totalPlayers = $called->sum(fn (QueueEntry $entry) => $this->playersIn($entry));

            if ($totalPlayers !== $court->max_players) {
                throw ValidationException::withMessages([
                    'court' => ["Court is not full: {$totalPlayers}/{$court->max_players} players confirmed."],
                ]);
            }

            [$teamAUserIds, $teamBUserIds] = $this->formTeams($called->all(), (int) ($court->max_players / 2));

            $match = Game::create([
                'court_id' => $court->id,
                'status' => MatchStatus::Ongoing,
                'started_at' => now(),
            ]);

            $teamA = Team::create(['match_id' => $match->id]);
            $teamB = Team::create(['match_id' => $match->id]);
            $teamA->users()->attach($teamAUserIds);
            $teamB->users()->attach($teamBUserIds);

            foreach ($called as $entry) {
                $entryPlayerIds = $entry->user ? [$entry->user_id] : $entry->group->users->pluck('id')->all();
                $teamId = array_intersect($teamAUserIds, $entryPlayerIds) ? $teamA->id : $teamB->id;

                $entry->update([
                    'status' => QueueStatus::OnCourt,
                    'team_id' => $teamId,
                ]);
            }

            return [
                'match' => $match->load(['teams.users', 'court']),
                'entries' => $called->map(fn (QueueEntry $entry) => $this->compactEntry($entry))->values()->all(),
            ];
        });

        $this->broadcastQueueChanged($court, 'confirmed', entries: $result['entries']);

        return $result['match'];
    }

    /**
     * Complete a match with the two team scores: resolve the winner, update
     * player stats, requeue the teams per the court's rotation rule and fill
     * the court with the next group.
     *
     * @throws ValidationException
     */
    public function completeMatch(Game $match, int $scoreA, int $scoreB): Game
    {
        $result = DB::transaction(function () use ($match, $scoreA, $scoreB) {
            $match = Game::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status !== MatchStatus::Ongoing) {
                throw ValidationException::withMessages([
                    'match' => ['This match is not ongoing.'],
                ]);
            }

            if ($scoreA === $scoreB) {
                throw ValidationException::withMessages([
                    'score' => ['Scores cannot be tied.'],
                ]);
            }

            $teams = $match->teams()->orderBy('id')->get();

            if ($teams->count() !== 2) {
                throw ValidationException::withMessages([
                    'match' => ['A match must have exactly two teams.'],
                ]);
            }

            [$teamA, $teamB] = [$teams[0], $teams[1]];
            $teamA->update(['score' => $scoreA]);
            $teamB->update(['score' => $scoreB]);

            $winner = $scoreA > $scoreB ? $teamA : $teamB;
            $loser = $winner->is($teamA) ? $teamB : $teamA;

            $match->update([
                'status' => MatchStatus::Completed,
                'winner_team_id' => $winner->id,
                'ended_at' => now(),
            ]);

            User::whereIn('id', $winner->users()->pluck('users.id')->all())->increment('wins');
            User::whereIn('id', $loser->users()->pluck('users.id')->all())->increment('losses');

            $court = $match->court;

            // A match can outlive its court (court_id is nullable/nullOnDelete);
            // rotation and refill only apply while the court still exists.
            if ($court) {
                $this->resolvePlayedEntries($match, $winner);

                $calledBefore = $this->calledCount($court);
                $called = $this->fillCourt($court);
            } else {
                $calledBefore = 0;
                $called = [];
            }

            return [
                'court' => $court,
                'winner' => $winner,
                'called' => $called,
                'calledBefore' => $calledBefore,
                // The inner, freshly-updated match (status = completed).
                'match' => $match->load(['teams.users', 'court']),
            ];
        });

        // The court is free again — listeners re-render the court status.
        if ($result['court'] !== null) {
            MatchEnded::dispatch(
                courtId: (int) $result['court']->id,
                matchId: (int) $result['match']->id,
                winnerTeamId: (int) $result['winner']->id,
                scoreA: $scoreA,
                scoreB: $scoreB,
                endedAt: now()->toISOString(),
            );

            $this->broadcastQueueChanged($result['court'], 'completed');
            $this->broadcastCourtCalledIfNew($result['court'], $result['called'], $result['calledBefore']);
        }

        return $result['match'];
    }

    /**
     * Auto-skip every called entry that did not confirm within the timeout and
     * refill the affected courts. Returns the number of entries skipped.
     */
    public function skipExpiredCalls(): int
    {
        $threshold = now()->subMinutes(self::CALL_TIMEOUT_MINUTES);

        $expiredByCourt = QueueEntry::where('status', QueueStatus::Called)
            ->where('called_at', '<=', $threshold)
            ->get()
            ->groupBy('court_id');

        $skipped = 0;

        foreach ($expiredByCourt as $courtId => $entries) {
            $result = DB::transaction(function () use ($courtId, $entries) {
                $court = Court::whereKey($courtId)->lockForUpdate()->firstOrFail();

                QueueEntry::whereKey($entries->pluck('id'))
                    ->where('status', QueueStatus::Called)
                    ->lockForUpdate()
                    ->update([
                        'status' => QueueStatus::Skipped,
                        'position' => null,
                        'resolved_at' => now(),
                    ]);

                $calledBefore = $this->calledCount($court);
                $called = $this->fillCourt($court);

                return compact('court', 'called', 'calledBefore');
            });

            $this->broadcastQueueChanged($result['court'], 'expired');
            $this->broadcastCourtCalledIfNew($result['court'], $result['called'], $result['calledBefore']);

            $skipped += $entries->count();
        }

        return $skipped;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Call waiting units until the court is full (called units count as occupied).
     * Ordering is FIFO by position, or by 0.5 skill bucket when skill_grouping is
     * enabled on the court. Units that would overshoot the remaining slots are
     * skipped (e.g. a 4-player group when 2 slots remain).
     *
     * @return QueueEntry[]
     */
    private function fillCourt(Court $court): array
    {
        $called = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Called)
            ->with(['user', 'group.users'])
            ->lockForUpdate()
            ->get();

        $occupied = $called->sum(fn (QueueEntry $entry) => $this->playersIn($entry));
        $need = $court->max_players - $occupied;

        if ($need <= 0) {
            return $called->all();
        }

        $waiting = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->with(['user', 'group.users'])
            ->lockForUpdate()
            ->get();

        $waiting = $waiting->sortBy('position')->values();

        $selected = $this->selectUnits($waiting, $need, $court->skill_grouping);

        if ($selected === null) {
            return $called->all();
        }

        foreach ($selected as $entry) {
            $entry->update([
                'status' => QueueStatus::Called,
                'position' => null,
                'called_at' => now(),
            ]);
        }

        // Keep the remaining waiting line compact after a call.
        $this->renumber($court);

        return $called->merge($selected)->all();
    }

    /**
     * Apply the court rotation rule to the entries that just played.
     */
    private function resolvePlayedEntries(Game $match, Team $winner): void
    {
        $court = $match->court;

        $played = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::OnCourt)
            ->whereIn('team_id', $match->teams()->pluck('teams.id'))
            ->lockForUpdate()
            ->get();

        $winnerIds = $played->where('team_id', $winner->id)->pluck('id')->all();
        $loserIds = $played->where('team_id', '<>', $winner->id)->pluck('id')->all();

        switch ($court->rotation_rule) {
            case CourtRotationRule::FourOnFourOff:
                // Everyone rotates off: whole court to the back of the line.
                $this->requeue($court, frontIds: [], backIds: [...$winnerIds, ...$loserIds]);
                break;

            case CourtRotationRule::LosersOut:
                // Winners keep their spot; losers are out of the queue.
                foreach ($loserIds as $id) {
                    QueueEntry::whereKey($id)->update([
                        'status' => QueueStatus::Completed,
                        'position' => null,
                        'resolved_at' => now(),
                    ]);
                }
                $this->requeue($court, frontIds: $winnerIds, backIds: []);
                break;

            case CourtRotationRule::WinnersStay:
            default:
                // Winners jump the line; losers go to the back.
                $this->requeue($court, frontIds: $winnerIds, backIds: $loserIds);
                break;
        }
    }

    /**
     * Requeue entries: frontIds take the top positions, backIds append at the end,
     * then all waiting positions are compacted to 0..n-1.
     *
     * Existing waiting entries are shifted up first so the front positions are
     * free for the winners (keeps the unique (court_id, position) index valid and
     * satisfies the position/status CHECK at every statement).
     */
    private function requeue(Court $court, array $frontIds = [], array $backIds = []): void
    {
        QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->update(['position' => DB::raw('position + '.self::POSITION_SHIFT)]);

        foreach ($frontIds as $index => $id) {
            QueueEntry::whereKey($id)->update([
                'status' => QueueStatus::Waiting,
                'position' => $index,
                'resolved_at' => null,
            ]);
        }

        // Back entries start just above the highest shifted position so they can
        // never collide with the waiting entries moved out of the way.
        $maxPosition = (int) (QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->max('position') ?? self::POSITION_SHIFT - 1);

        foreach ($backIds as $index => $id) {
            QueueEntry::whereKey($id)->update([
                'status' => QueueStatus::Waiting,
                'position' => $maxPosition + 1 + $index,
                'resolved_at' => null,
            ]);
        }

        $this->renumber($court);
    }

    /**
     * Compact waiting positions to 0..n-1, keeping FIFO order.
     */
    private function renumber(Court $court): void
    {
        $entries = QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        $index = 0;

        foreach ($entries as $entry) {
            if ((int) $entry->position === $index) {
                $index++;

                continue;
            }

            $entry->update(['position' => $index++]);
        }
    }

    /**
     * Choose which waiting units fill the court.
     *
     * FIFO: the earliest-joined units (by position) that exactly fill the slots.
     *
     * Skill grouping: anchor on the earliest-joined unit and fill from units
     * whose skill is within 0.25 of the anchor (so all four span <= 0.5). If the
     * anchor's skill cluster cannot fill the court, falls back to FIFO so the
     * queue never starves.
     *
     * @param  Collection<int, QueueEntry>  $waiting
     * @return QueueEntry[]|null null when the court cannot be filled
     */
    private function selectUnits($waiting, int $capacity, bool $skillGrouping): ?array
    {
        if ($skillGrouping && $waiting->isNotEmpty()) {
            $anchor = $waiting->first();
            $anchorSkill = $this->unitSkill($anchor);

            $cluster = $waiting
                ->filter(fn (QueueEntry $entry) => abs($this->unitSkill($entry) - $anchorSkill) <= 0.25)
                ->values();

            $selected = $this->greedyFill($cluster, $capacity);

            if ($selected !== null) {
                return $selected;
            }
        }

        return $this->greedyFill($waiting, $capacity);
    }

    /**
     * Greedily pick units (in order) that sum to exactly the capacity, skipping
     * units that would overshoot. Returns null when no exact fill is possible.
     *
     * @param  Collection<int, QueueEntry>  $entries
     * @return QueueEntry[]|null
     */
    private function greedyFill($entries, int $capacity): ?array
    {
        $selected = [];
        $total = 0;

        foreach ($entries as $entry) {
            $size = $this->playersIn($entry);

            if ($total + $size > $capacity) {
                continue;
            }

            $selected[] = $entry;
            $total += $size;

            if ($total === $capacity) {
                return $selected;
            }
        }

        return $total === $capacity ? $selected : null;
    }

    /**
     * Split a full court of players into two balanced teams, preserving
     * 2-player groups as whole teams and splitting 4-player groups.
     *
     * @param  QueueEntry[]  $entries
     * @return array{0: int[], 1: int[]} team A and team B user ids
     */
    private function formTeams(array $entries, int $teamSize): array
    {
        $units = [];

        foreach ($entries as $entry) {
            $playerIds = $entry->user ? [$entry->user_id] : $entry->group->users->pluck('id')->all();
            $count = count($playerIds);

            if ($count > $teamSize) {
                // e.g. a 4-player group on a doubles court: split by skill.
                $sorted = collect($playerIds)
                    ->sortByDesc(fn (int $id) => $this->skillRatingOf($id))
                    ->values()
                    ->all();

                foreach (array_chunk($sorted, $teamSize) as $chunk) {
                    $units[] = ['members' => $chunk, 'skill' => $this->averageSkill($chunk)];
                }
            } else {
                $units[] = ['members' => $playerIds, 'skill' => $this->averageSkill($playerIds)];
            }
        }

        // Whole-team units first so they claim their sides, then solos.
        usort($units, fn (array $a, array $b) => count($b['members']) <=> count($a['members']));

        $teamA = [];
        $teamB = [];
        $countA = 0;
        $countB = 0;
        $sumA = 0.0;
        $sumB = 0.0;

        foreach ($units as $unit) {
            $size = count($unit['members']);
            $fitsA = $countA + $size <= $teamSize;
            $fitsB = $countB + $size <= $teamSize;

            $goA = $fitsA && (! $fitsB || $sumA <= $sumB);

            if ($goA) {
                $teamA = array_merge($teamA, $unit['members']);
                $countA += $size;
                $sumA += $unit['skill'];
            } else {
                $teamB = array_merge($teamB, $unit['members']);
                $countB += $size;
                $sumB += $unit['skill'];
            }
        }

        return [$teamA, $teamB];
    }

    private function averageSkill(array $userIds): float
    {
        if ($userIds === []) {
            return 3.0;
        }

        $ratings = User::whereKey($userIds)->pluck('skill_rating')->all();
        $ratings = array_filter($ratings, fn ($rating) => $rating !== null);

        if ($ratings === []) {
            return 3.0;
        }

        return array_sum($ratings) / count($ratings);
    }

    private function skillRatingOf(int $userId): float
    {
        $rating = User::whereKey($userId)->value('skill_rating');

        return $rating === null ? 3.0 : (float) $rating;
    }

    /**
     * Representative skill of a unit: the player's rating, or the group's
     * average rating. Missing ratings default to 3.0.
     */
    private function unitSkill(QueueEntry $entry): float
    {
        $ratings = $entry->user
            ? [$entry->user->skill_rating]
            : $entry->group->users->pluck('skill_rating')->all();

        $ratings = array_filter($ratings, fn ($rating) => $rating !== null);

        $skill = $ratings === [] ? 3.0 : array_sum($ratings) / count($ratings);

        return (float) $skill;
    }

    /**
     * Number of players represented by an entry (solo = 1, group = members).
     */
    private function playersIn(QueueEntry $entry): int
    {
        return $entry->user ? 1 : count($entry->group->users);
    }

    /*
    |--------------------------------------------------------------------------
    | Broadcasting helpers
    |--------------------------------------------------------------------------
    |
    | All events are dispatched from the public methods AFTER their DB
    | transaction has committed, so subscribers only ever see committed state.
    | Payloads are kept minimal (counts + the waiting line) to limit bandwidth.
    */

    /** Number of queue units currently awaiting confirmation (status = called). */
    private function calledCount(Court $court): int
    {
        return (int) QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Called)
            ->count();
    }

    /**
     * Compact waiting/called/on_court counts for a court, used by QueueUpdated.
     *
     * @return array{waiting: int, called: int, on_court: int}
     */
    private function queueStats(Court $court): array
    {
        $counts = QueueEntry::where('court_id', $court->id)
            ->selectRaw('status, count(*) as total')
            ->whereIn('status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::OnCourt])
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'waiting' => (int) ($counts[QueueStatus::Waiting->value] ?? 0),
            'called' => (int) ($counts[QueueStatus::Called->value] ?? 0),
            'on_court' => (int) ($counts[QueueStatus::OnCourt->value] ?? 0),
        ];
    }

    /**
     * The waiting line as compact [entry_id, position] tuples (FIFO order).
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function waitingPositions(Court $court): array
    {
        return QueueEntry::where('court_id', $court->id)
            ->where('status', QueueStatus::Waiting)
            ->orderBy('position')
            ->get(['id', 'position'])
            ->map(fn (QueueEntry $entry) => [$entry->id, (int) $entry->position])
            ->all();
    }

    /**
     * Minimal representation of a queue entry for broadcast payloads: ids,
     * names and counts only — never emails, tokens or full resource objects.
     *
     * @return array<string, mixed>
     */
    private function compactEntry(QueueEntry $entry): array
    {
        $players = $entry->user !== null
            ? collect([$entry->user])
            : collect($entry->group?->users ?? []);

        $summary = [
            'id' => $entry->id,
            'status' => $entry->status->value,
            'position' => $entry->position,
            'players_count' => $entry->players_count,
            'players' => $players->map(
                fn (User $user) => ['id' => $user->id, 'name' => $user->name]
            )->values()->all(),
        ];

        if ($entry->team_id !== null) {
            $summary['team_id'] = $entry->team_id;
        }

        return $summary;
    }

    /**
     * Broadcast a compact queue-state change to the court's private channel.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function broadcastQueueChanged(Court $court, string $action, array $entries = []): void
    {
        QueueUpdated::dispatch(
            courtId: (int) $court->id,
            action: $action,
            counts: $this->queueStats($court),
            positions: $this->waitingPositions($court),
            entries: $entries,
        );
    }

    /**
     * Broadcast CourtCalled only when new units were actually summoned and the
     * court is now full (all called units together reach max_players) — avoids
     * duplicate summons for a batch that was already called earlier.
     *
     * @param  array<int, QueueEntry>  $called
     */
    private function broadcastCourtCalledIfNew(Court $court, array $called, int $calledBefore): void
    {
        if (count($called) <= $calledBefore) {
            return;
        }

        $totalPlayers = array_sum(array_map(
            fn (QueueEntry $entry) => $this->playersIn($entry),
            $called,
        ));

        if ($totalPlayers !== (int) $court->max_players) {
            return;
        }

        CourtCalled::dispatch(
            courtId: (int) $court->id,
            entries: array_map(fn (QueueEntry $entry) => $this->compactEntry($entry), $called),
            expiresAt: now()->addMinutes(self::CALL_TIMEOUT_MINUTES)->toISOString(),
        );
    }
}
