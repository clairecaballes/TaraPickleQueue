<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtResource;
use App\Models\Court;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourtController extends Controller
{
    /**
     * GET /api/courts
     *
     * All courts with the live state the dashboard needs in one request:
     * waiting line length + the currently in-progress match (teams + players).
     */
    public function index(): AnonymousResourceCollection
    {
        $courts = Court::query()
            ->with(['currentMatch.teams.users'])
            ->withCount([
                'queueEntries as waiting_entries_count' => fn ($query) => $query
                    ->where('status', QueueStatus::Waiting),
            ])
            ->orderBy('id')
            ->get();

        return CourtResource::collection($courts);
    }
}
