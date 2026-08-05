# Pickle Ta Bai! — Real-Time Engine (Laravel Reverb)

The queue engine pushes live updates over WebSockets with **Laravel Reverb**
(first-party, self-hosted, Pusher-protocol compatible). Clients subscribe with
**Laravel Echo** (pusher-js) and render directly from the event payloads — no
REST re-fetch needed for queue changes.

```
┌──────────┐   HTTP/REST (Sanctum)   ┌──────────────────┐
│ Clients  │ ◀────────────────────── │ Laravel app      │
│ (Echo)   │   WS ws://host:8080     │  - QueueService  │──▶ events
│          │ ◀────────────────────── │  - Reverb driver │
└────┬─────┘                         └──────────────────┘
     │                                     │
     └── private court.{courtId} ──────────┘  (authorized via /broadcasting/auth)
```

---

## Setup

```bash
composer require laravel/reverb:^1.0     # done — pulls pusher/pusher-php-server
php artisan config:publish broadcasting  # done — config/broadcasting.php
php artisan reverb:install               # done — config/reverb.php + .env keys
npm install --save-dev laravel-echo pusher-js
```

`.env` (already set):

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=725860
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Running

```bash
php artisan reverb:start            # the WebSocket server (port 8080)
php artisan queue:work              # broadcast jobs are queued — MUST be running
php artisan schedule:run            # feeds queue:skip-expired-calls (1/min)
npm run dev                         # Vite (Echo client)
```

> In production run Reverb under Supervisor and scale with Redis
> (`REVERB_SCALING_ENABLED=true`). Broadcast events ride the queue
> (`QUEUE_CONNECTION`), so a queue worker is mandatory.

### Route wiring (`bootstrap/app.php`)

```php
->withBroadcasting(
    channels: __DIR__.'/../routes/channels.php',
    attributes: ['middleware' => ['auth:sanctum']],
)
```

Registers `POST|GET /broadcasting/auth` (Sanctum-guarded — Echo sends the Bearer
token) and loads the private-channel definitions.

---

## Channels & authorization

| Channel | Type | Authorization (`routes/channels.php`) |
|---------|------|---------------------------------------|
| `court.{courtId}` | private | Any authenticated user, provided the court exists |

```php
Broadcast::channel('court.{courtId}', function (User $user, int $courtId) {
    return Court::whereKey($courtId)->exists();
});
```

Court queue state is club-wide info (shown on court-side displays), so any
authenticated user may watch. Tighten to staff/court-manager roles when
role-based authorization lands — the queue API has the same follow-up.

---

## Events

All events implement `ShouldBroadcast`, are dispatched **after the DB
transaction commits** (subscribers never see phantom state), and publish on the
private `court.{courtId}` channel.

| Event | File | Fired when |
|-------|------|-----------|
| `QueueUpdated` | `app/Events/QueueUpdated.php` | A player/group joins, leaves, is skipped, moves position, is called, or a finished match requeues them |
| `CourtCalled` | `app/Events/CourtCalled.php` | The court queue fills and a full batch (typically 4) is summoned |
| `MatchEnded` | `app/Events/MatchEnded.php` | A match is completed and the court becomes free |

Dispatch points (`app/Services/QueueService.php`):

| Service method | Events |
|----------------|--------|
| `join` | `QueueUpdated` (action `joined`) |
| `leave` | `QueueUpdated` (`left`) · `CourtCalled` when the court refills |
| `skip` | `QueueUpdated` (`skipped`) · `CourtCalled` when it refills |
| `callNextUp` | `QueueUpdated` (`called`) · `CourtCalled` when full |
| `confirmCall` | `QueueUpdated` (`confirmed`, entries carry `team_id`) |
| `completeMatch` | `MatchEnded` · `QueueUpdated` (`completed`) · `CourtCalled` when the next group is called |
| `skipExpiredCalls` | `QueueUpdated` (`expired`, per court) · `CourtCalled` when it refills |

### Bandwidth notes

Payloads are intentionally minimal:

- Only **counts + the waiting line** (`[entry_id, position]` pairs) are sent —
  a 10-player line is ~150 bytes instead of full API resources.
- Affected entries are compact (`id`, `status`, `position`, `players_count`,
  `players: [{id, name}]`, optional `team_id`) — never emails, tokens, or full
  serialized models.
- `CourtCalled` dedupes: it only fires when a **new** unit was summoned and the
  court is **full** (never re-fires for an already-called batch).
- In `leave`/`skip`/`completeMatch`/`skipExpiredCalls`, the `QueueUpdated`
  counts already reflect the post-refill state (the refill runs before the
  event is dispatched), so clients should treat every payload as the current
  snapshot.

---

## Sample payloads

### QueueUpdated — player joins (`action: joined`)

```json
{
  "court_id": 1,
  "action": "joined",
  "waiting_count": 4,
  "called_count": 0,
  "on_court_count": 0,
  "positions": [[12, 0], [13, 1], [14, 2], [15, 3]],
  "entries": [
    {
      "id": 15,
      "status": "waiting",
      "position": 3,
      "players_count": 1,
      "players": [{ "id": 7, "name": "Ada" }]
    }
  ]
}
```

### QueueUpdated — line moves after a match (`action: completed`)

The requeue (rotation rule) is reflected in `positions`; no `entries` needed.

```json
{
  "court_id": 1,
  "action": "completed",
  "waiting_count": 5,
  "called_count": 4,
  "on_court_count": 0,
  "positions": [[18, 0], [19, 1], [20, 2], [21, 3], [22, 4]]
}
```

### CourtCalled — four players summoned

```json
{
  "court_id": 1,
  "entries": [
    { "id": 10, "status": "called", "position": null, "players_count": 1, "players": [{ "id": 3, "name": "Bo" }] },
    { "id": 11, "status": "called", "position": null, "players_count": 1, "players": [{ "id": 4, "name": "Cy" }] },
    { "id": 12, "status": "called", "position": null, "players_count": 1, "players": [{ "id": 5, "name": "Di" }] },
    { "id": 13, "status": "called", "position": null, "players_count": 1, "players": [{ "id": 6, "name": "Ed" }] }
  ],
  "expires_at": "2026-08-02T14:05:00.000000Z"
}
```

`expires_at` = `called_at + 2 minutes` (`QueueService::CALL_TIMEOUT_MINUTES`);
after it, `queue:skip-expired-calls` skips the batch and a `QueueUpdated`
(`expired`) follows.

> **Refill re-summon:** if a partially-called court is topped up (a leave,
> skip or expiry makes room), `CourtCalled` fires again with the *entire*
> current batch and a fresh `expires_at`. Treat `expires_at` as the deadline
> for the whole batch; clients can diff `entries[].id` against what they
> already showed to alert only the newcomers.

### MatchEnded — court free again

```json
{
  "court_id": 1,
  "match_id": 42,
  "winner_team_id": 3,
  "score_a": 11,
  "score_b": 5,
  "ended_at": "2026-08-02T14:32:00.000000Z"
}
```

---

## Client (Laravel Echo)

`resources/js/echo.js` already configures Echo (token from `localStorage.auth_token`).

```js
import Echo from 'laravel-echo';
// ... echo.js sets window.Echo ...

window.Echo.private(`court.${courtId}`)
    .listen('QueueUpdated', ({ action, positions, waiting_count, entries }) => {
        // Re-render the line from `positions`; animate per action/entries.
    })
    .listen('CourtCalled', ({ entries, expires_at }) => {
        // Flash the court display: "Summoned — confirm within 2:00".
    })
    .listen('MatchEnded', ({ score_a, score_b, winner_team_id }) => {
        // Show the result, then the court is free for the next call.
    });
```

> Event names on the wire are the class basenames (`QueueUpdated`, etc.) — Echo
> prefixes `App\Events\` automatically, so `.listen('QueueUpdated', ...)` works.

---

## Files

```
app/Events/QueueUpdated.php      queue change notification (minimal state)
app/Events/CourtCalled.php       full-court summons + confirmation deadline
app/Events/MatchEnded.php        court freed + result
routes/channels.php              private court.{id} authorization
bootstrap/app.php                /broadcasting/auth (auth:sanctum) + channels load
config/broadcasting.php          published; reverb connection block
config/reverb.php                published by reverb:install
resources/js/echo.js             Echo/reverb client bootstrap
```
