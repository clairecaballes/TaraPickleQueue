# Pickle Ta Bai! — Authentication & API Scaffold

Stack: **Laravel 11** API + **Laravel Sanctum** (token-based, Bearer)

---

## Setup (run inside a fresh Laravel 11 app)

The code in `app/` and `routes/` is written to slot into a stock Laravel 11 app:

```bash
composer create-project laravel/laravel .     # scaffold the app in this directory
php artisan install:api                       # installs Sanctum, publishes its migration,
                                              # creates routes/api.php and wires it into bootstrap/app.php
```

Notes:

- `install:api` generates a sample `routes/api.php`; **replace it with the one provided here.**
- `install:api` registers the api routes in `bootstrap/app.php` (`->withRouting(api: ...)`) and prepends Sanctum's stateful middleware to the `api` group — no manual wiring needed for plain token auth.
- The `User` model already uses `Laravel\Sanctum\HasApiTokens`.
- Run `php artisan migrate` (the `personal_access_tokens` table is created by Sanctum's published migration).
- Send tokens as `Authorization: Bearer <token>`.

---

## Endpoints

### Public

| Method | URI                  | Body (JSON)                                                                                | Success |
|--------|----------------------|--------------------------------------------------------------------------------------------|---------|
| POST   | `/api/auth/register` | `name`, `email`, `password`, `password_confirmation`, optional `skill_rating`, `phone`     | 201     |
| POST   | `/api/auth/login`    | `email`, `password` (rate-limited to 5/min)                                                | 200     |

### Protected (`auth:sanctum`)

| Method | URI               | Body (JSON)                                                              | Success |
|--------|-------------------|--------------------------------------------------------------------------|---------|
| POST   | `/api/auth/logout` | —                                                                        | 200     |
| GET    | `/api/auth/me`     | —                                                                        | 200     |
| GET    | `/api/profile`     | —                                                                        | 200     |
| PATCH  | `/api/profile`     | any of: `name`, `skill_rating`, `phone`, `avatar` (partial update)       | 200     |

---

## Response shapes

**Register** (201) and **Login** (200) share the same shape (message differs):

```json
{
  "message": "Registration successful.",
  "access_token": "1|abcdef...",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "Ada", "email": "ada@example.com", "skill_rating": 3.5, ... }
}
```

**User** (`UserResource`) — sanitized, never exposes `password` / `remember_token`:

```json
{
  "id": 1,
  "name": "Ada",
  "email": "ada@example.com",
  "skill_rating": 3.5,
  "phone": "+1 555 0100",
  "avatar": "https://cdn.example.com/avatars/1.png",
  "created_at": "2026-08-02T10:00:00.000000Z",
  "updated_at": "2026-08-02T10:00:00.000000Z"
}
```

**Court** (`CourtResource`):

```json
{
  "id": 1,
  "name": "Court 1",
  "location": "North gym",
  "play_type": "doubles",
  "max_players": 4,
  "is_active": true,
  "queue_length": 5
}
```

`queue_length` is only present when the court was loaded with `->withCount('queueEntries')`.

**Queue entry** (`QueueEntryResource`) — either `user` or `group` is populated (never both):

```json
{
  "id": 12,
  "court_id": 1,
  "status": "waiting",
  "position": 3,
  "players_count": 2,
  "user": { "id": 4, "name": "Bob", ... },
  "group": null,
  "joined_at": "2026-08-02T09:00:00.000000Z",
  "called_at": null,
  "resolved_at": null
}
```

**Validation errors** — Laravel's standard 422 shape:

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email has already been taken."] }
}
```

**401** when the token is missing/invalid; **429** when rate-limited.

---

## Validation rules

| Field           | Register            | Update profile        |
|-----------------|---------------------|-----------------------|
| `name`          | required, max:255   | sometimes, max:255    |
| `email`         | required, unique    | —                     |
| `password`      | required, min:8, confirmed | —              |
| `skill_rating`  | nullable, 1–5, ≤1 decimal | sometimes, nullable, 1–5, ≤1 decimal |
| `phone`         | nullable, max:32    | sometimes, nullable, max:32 |
| `avatar`        | —                   | sometimes, nullable, URL, max:2048 |

`skill_rating` mirrors the database CHECK constraint (1.0 – 5.0) from Prompt 1.1.

---

## Files

```
routes/api.php                                  Auth + profile routes
app/Http/Controllers/Api/AuthController.php     register, login, logout, me
app/Http/Controllers/Api/ProfileController.php  show, update
app/Http/Requests/Auth/RegisterRequest.php
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/Profile/UpdateProfileRequest.php
app/Http/Resources/UserResource.php
app/Http/Resources/CourtResource.php
app/Http/Resources/GroupResource.php
app/Http/Resources/QueueEntryResource.php
```
