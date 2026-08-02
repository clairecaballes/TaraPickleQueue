# TaraPickle — Database Schema & ERD

Stack: **Laravel 11** (PHP 8.3) · **PostgreSQL** or **MySQL/MariaDB**

---

## ERD (Mermaid)

```mermaid
erDiagram
    users ||--o{ queue_entries : "solo joins"
    users ||--o{ group_user : "member of"
    users ||--o{ team_user : "plays on"
    groups ||--o{ group_user : "contains"
    groups ||--o{ queue_entries : "party joins"
    courts ||--o{ queue_entries : "has line"
    courts ||--o{ matches : "hosts"
    matches ||--o{ teams : "two sides"
    matches o|--o| teams : "winner_team_id"
    teams ||--o{ team_user : "has players"

    users {
        bigint id PK
        string name
        string email UK
        string password
        decimal(2,1) skill_rating "1.0 - 5.0, CHECK"
        string phone
        string avatar
    }
    courts {
        bigint id PK
        string name
        string location
        enum play_type "singles | doubles"
        tinyint max_players "2 or 4"
        boolean is_active
    }
    groups {
        bigint id PK
        string name
        bigint created_by_user_id FK
    }
    group_user {
        bigint group_id PK, FK
        bigint user_id PK, FK
    }
    queue_entries {
        bigint id PK
        bigint court_id FK
        bigint user_id FK "xor group_id"
        bigint group_id FK "xor user_id"
        tinyint players_count "1 | 2 | 4"
        int position "NULL once off the line"
        enum status "waiting | on_court | completed | skipped"
        timestamp joined_at
        timestamp called_at
        timestamp resolved_at
    }
    matches {
        bigint id PK
        bigint court_id FK "nullable"
        enum status "ongoing | completed | cancelled"
        bigint winner_team_id FK "nullable"
        timestamp started_at
        timestamp ended_at
    }
    teams {
        bigint id PK
        bigint match_id FK
        tinyint score "nullable"
    }
    team_user {
        bigint team_id PK, FK
        bigint user_id PK, FK
    }
```

---

## ASCII overview

```
USERS ──────┐                         ┌────── COURTS
            │  group_user             │   │
            ├──< user_id  >───────────┤   │ queue_entries
            │   group_id >──┐         │   │   court_id
            │               │         │   ├──< user_id / group_id (xor)
            │               ▼         │   │   position, status
            └────────────── GROUPS ───┘   │
                                          │ matches
                                          │   court_id, winner_team_id
                                          └──< teams ── team_user ──< users
```

---

## Tables

### `users` *(extends Laravel's default auth table)*
| column        | type            | notes |
|---------------|-----------------|-------|
| id            | bigint PK       | |
| name / email  | string          | email unique (framework) |
| password      | string          | hashed (framework) |
| skill_rating  | decimal(2,1)    | nullable, CHECK 1.0–5.0, indexed |
| phone         | string(32)      | nullable, indexed |
| avatar        | string(2048)    | nullable (URL/path) |

### `courts`
| column      | type            | notes |
|-------------|-----------------|-------|
| id          | bigint PK       | |
| name        | string          | |
| location    | string          | nullable |
| play_type   | enum            | `singles` / `doubles` (default doubles) |
| max_players | tinyint         | default 4 (2 for singles, 4 for doubles) |
| is_active   | boolean         | default true; indexed |

### `groups` (parties of 2 or 4)
| column             | type       | notes |
|--------------------|------------|-------|
| id                 | bigint PK  | |
| name               | string     | nullable, e.g. "The Pickle Crew" |
| created_by_user_id | bigint FK  | nullable → users, `ON DELETE SET NULL`, indexed |
| timestamps         |            | |

### `group_user` (pivot)
- composite PK `(group_id, user_id)`; both FKs `ON DELETE CASCADE`
- index on `user_id` for "all groups of a user"
- group size **2 or 4** is enforced in the app layer (cross-table CHECK unsupported)

### `queue_entries` (next-up lines)
| column        | type            | notes |
|---------------|-----------------|-------|
| id            | bigint PK       | |
| court_id      | bigint FK       | → courts, `ON DELETE CASCADE` |
| user_id       | bigint FK       | nullable → users, **xor** group_id (CHECK) |
| group_id      | bigint FK       | nullable → groups, **xor** user_id (CHECK) |
| players_count | tinyint         | 1 / 2 / 4, CHECK 1–4 |
| position      | unsigned int    | nullable; unique per court, NULL once off the line |
| status        | enum            | `waiting` → `on_court` → `completed` / `skipped` |
| joined_at     | timestamp       | default now |
| called_at     | timestamp       | nullable |
| resolved_at   | timestamp       | nullable |

Constraints & indexes
- `UNIQUE (court_id, position)` — one unit per slot per court
- `INDEX (court_id, status)` — per-court line + board filters
- CHECKs: exactly-one-of `user_id`/`group_id`; `players_count BETWEEN 1 AND 4`; `(position IS NULL) = (status <> 'waiting')`

> A doubles court needs 4 players; if two 2-player groups are called together they occupy **two** `on_court` rows — capacity is a derived value (SUM of `players_count` of `on_court` rows vs `courts.max_players`).

### `matches` + `teams` + `team_user`
Normalized so a match always has exactly two sides, each of 1–2 players, with per-team scores:

| table       | columns | notes |
|-------------|---------|-------|
| matches     | id, court_id FK (nullable, `SET NULL`), status enum, started_at, ended_at, winner_team_id FK (nullable, → teams) | history kept if a court is deleted |
| teams       | id, match_id FK (`CASCADE`), score tinyint nullable | |
| team_user   | composite PK `(team_id, user_id)`, both FKs `CASCADE` | 1 player = singles, 2 = doubles |

- `winner_team_id` is added in a follow-up migration to avoid a circular FK (`matches → teams → matches`).
- `INDEX (court_id, started_at)` for per-court match history.

---

## Key design decisions

1. **`Match` model is named `Game`** — `match` is a reserved keyword in PHP 8 (verified: `class Match {}` is a parse error). The model maps to the `matches` table, so the DB schema keeps the name you specified. See `app/Models/Game.php`.
2. **Teams are normalized** (matches → teams → team_user) rather than `player_a1..player_b2` columns: handles singles (1v1) and doubles (2v2) with the same shape, and lets "winning team / losing team" and per-team scores be real foreign keys.
3. **Queue entries are polymorphic over solo user or group** via two nullable FKs + a CHECK that exactly one is set. This keeps "join as a party" and "join solo" on the same line.
4. **`position` is denormalized but nullable + unique per court** — a fast queue board without N+1 ordering queries; NULLs are permitted in both MySQL and PostgreSQL, so rows off the line don't conflict.
5. **Enums at both layers** — DB `enum()` columns (native on MySQL; varchar+CHECK on PostgreSQL) plus PHP backed-enums cast on the models for type-safe code.
6. **Explicit indexes on every FK column** — required for good join performance on PostgreSQL (InnoDB auto-indexes FKs, Postgres does not).
7. **CHECK constraints via `DB::statement()`** — Laravel 11 has no fluent `$table->check()` (verified against framework source); the two CHECKs use driver-aware SQL where syntax differs (`::int` on Postgres vs boolean arithmetic on MySQL).

---

## Postgres vs MySQL notes

| feature                       | behavior |
|-------------------------------|----------|
| `enum()` column               | MySQL: native ENUM; Postgres: varchar + CHECK (same constraints) |
| `after()` column modifier     | MySQL: applies; Postgres: ignored (harmless) |
| exactly-one-party CHECK       | handled with `::int` cast (Postgres) vs boolean arithmetic (MySQL) |
| NULLs in unique index         | allowed on both (multiple NULLs OK) |
