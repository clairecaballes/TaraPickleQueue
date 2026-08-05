# Pickle Ta Bai! — Queue Engine & Court Rules

The core logic lives in **`app/Services/QueueService.php`** and is wrapped by the
queue/match API controllers. Every operation runs inside a **DB transaction with
pessimistic row locks** (`lockForUpdate`) so concurrent joins/calls can never
double-book a player or assign the same queue position twice.

---

## Entry lifecycle

```
waiting ──(callNextUp)──▶ called ──(confirmCall)──▶ on_court ──(completeMatch)──▶ completed
                            │                           │
                            └──(2 min timeout)──▶ skipped ◀──(manual skip)──────┘
```

- `called` = the unit was called to court and has a **2-minute window** to confirm.
- `match.started_at` is recorded when the call is confirmed (`confirmCall`), which
  is when play actually begins.

---

## Service methods

| Method | What it does |
|--------|--------------|
| `join(Court, User, ?Group)` | Solo or group join. Rejects if any player is already `waiting`/`called`/`on_court` on any court, if the caller is not a group member, or if the group (2 or 4) exceeds court capacity. Locks all participant user rows to serialize concurrent joins. |
| `leave(QueueEntry)` | Remove a waiting/called entry (blocked while `on_court`). |
| `skip(QueueEntry)` | Manual skip of waiting/called entries; refills the court. |
| `callNextUp(Court)` | Call units until the court is full (FIFO by position, or skill-grouped). |
| `confirmCall(Court)` | Move the `called` batch to `on_court`, create the match (`started_at`), split players into 2 teams, assign `team_id` on entries. |
| `completeMatch(Game, scoreA, scoreB)` | Set scores + winner, mark completed, increment `wins`/`losses`, requeue teams per the court's rotation rule, and immediately call the next group. |
| `skipExpiredCalls()` | Auto-skip `called` entries older than 2 minutes and refill their courts. |

---

## Queue sorting

- **FIFO (default):** the earliest-joined units (by `position`) that exactly fill
  the court are called. Units that would overshoot the remaining slots are
  skipped (e.g. a 4-player group with only 2 slots left).
- **Skill grouping** (`courts.skill_grouping = true`): anchor on the
  earliest-joined unit and fill from units within **±0.25** of its rating, so the
  four called players span at most **0.5**. If the anchor's skill cluster can't
  fill the court, it falls back to FIFO — the queue never starves.

## Team formation (`confirmCall`)

- A 2-player group is kept intact as one team.
- A 4-player group is split into two balanced teams by skill.
- Solo players are assigned to the side with the lower accumulated skill
  (balanced teams).
- `score_a`/`score_b` map to Team A / Team B (creation order).

---

### Edge cases

- **Group of 4**: members are split across the two teams for the match (stats are
  recorded per player), but the group *rotates as a single queue unit* — the
  whole group is requeued/completed per the court rule based on the team its
  entry was assigned to. This keeps the group model intact at the cost of
  per-member rotation precision.
- **Deleted court**: a match whose court was deleted (nullable `court_id`) can
  still be completed — scores and stats are recorded, but queue rotation is
  skipped.
- **Partial calls**: units that would overshoot the remaining court slots are
  passed over; the court is only called when it can be filled exactly.

---

## Rotation rules (`courts.rotation_rule`)

After a match completes:

| Rule | Winning team | Losing team |
|------|--------------|-------------|
| `winners_stay` | requeued at the **front** of the line | requeued at the **back** |
| `four_on_four_off` | requeued at the back (winner team first) | requeued at the back |
| `losers_out` | requeued at the front | marked `completed` (out of the queue) |

The next group is called automatically after completion.

---

## Concurrency

- `join`: locks the court row, then the user rows of all participants, then
  checks for active entries — two simultaneous joins by the same player on
  different courts cannot both succeed.
- `callNextUp`/`confirmCall`/`completeMatch`: lock the court (and the relevant
  queue entries / match) before mutating.
- Requeueing shifts waiting positions by a large offset before assigning front
  positions, keeping the `UNIQUE (court_id, position)` index valid and the
  position/status CHECK satisfied at every statement.

## The 2-minute timeout

- `php artisan queue:skip-expired-calls` skips `called` entries whose
  `called_at` is older than 2 minutes and refills the court.
- Registered to run every minute in `routes/console.php` (the app scheduler must
  run `php artisan schedule:run` every minute).

---

## API endpoints (all `auth:sanctum`)

| Method | URI | Purpose |
|--------|-----|---------|
| GET    | `/api/courts/{court}/queue` | View the line |
| POST   | `/api/courts/{court}/queue` | Join (body: optional `group_id`) |
| DELETE | `/api/queue/{queueEntry}` | Leave |
| POST   | `/api/queue/{queueEntry}/skip` | Manual skip |
| POST   | `/api/courts/{court}/next-up` | Call the next group |
| POST   | `/api/courts/{court}/confirm-call` | Confirm call → start match |
| POST   | `/api/matches/{game}/complete` | Complete (body: `score_a`, `score_b`) |

> Note: `next-up`, `confirm-call` and `complete` are admin-grade actions; role
> based authorization (policies / roles) is a follow-up.

## Schema changes (Prompt 2.1)

- `courts.rotation_rule` (enum) + `courts.skill_grouping` (bool)
- `users.wins`, `users.losses` (counters)
- `queue_entries.status` gains `called`
- `queue_entries.team_id` (FK → teams) links an on-court unit to its team
