<?php

use App\Models\Court;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — TaraPickle
|--------------------------------------------------------------------------
|
| Every real-time event (QueueUpdated, CourtCalled, MatchEnded) is published
| on a per-court private channel:  court.{courtId}
|
| Authorization: any authenticated user may watch a court's live channel, as
| long as the court exists. Queue/court state is club-wide information shown
| on the court-side displays. Tighten this to staff / court-manager roles
| when role-based authorization lands (the queue API has the same follow-up).
|
*/

Broadcast::channel('court.{courtId}', function (User $user, int $courtId) {
    return Court::whereKey($courtId)->exists();
});
