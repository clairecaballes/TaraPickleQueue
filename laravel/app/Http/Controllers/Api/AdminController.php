<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddPlayerRequest;
use App\Http\Requests\Admin\SearchUsersRequest;
use App\Http\Requests\Queue\ReorderQueueRequest;
use App\Http\Resources\QueueEntryResource;
use App\Http\Resources\UserResource;
use App\Models\Court;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Organizer / court-manager actions. Every route in this controller is
 * registered behind `auth:sanctum` + `can:manage-court`.
 */
class AdminController extends Controller
{
    public function __construct(private readonly QueueService $queue) {}

    /**
     * GET /api/admin/users/search?q=
     *
     * Find a player by name or phone so the organizer can add them manually.
     */
    public function searchUsers(SearchUsersRequest $request): AnonymousResourceCollection
    {
        $q = $request->validated('q');

        $users = User::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"))
            ->limit(10)
            ->get();

        return UserResource::collection($users);
    }

    /**
     * POST /api/admin/courts/{court}/queue/add
     *
     * Manually place a player (by user_id) into the court's queue.
     */
    public function addToQueue(Court $court, AddPlayerRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->validated('user_id'));

        $entry = $this->queue->join($court, $user);

        return response()->json([
            'message' => 'Player added to the queue.',
            'queue_entry' => new QueueEntryResource($entry->load(['court', 'user', 'group.users'])),
        ], 201);
    }

    /**
     * PATCH /api/admin/courts/{court}/queue/reorder
     *
     * Drag-and-drop override: ordered_ids is the full waiting line, top to
     * bottom. Positions are reassigned 0..n-1 in that order.
     */
    public function reorderQueue(Court $court, ReorderQueueRequest $request): AnonymousResourceCollection
    {
        $this->queue->reorder($court, $request->validated('ordered_ids'));

        $entries = QueueEntry::where('court_id', $court->id)
            ->with(['court', 'user', 'group.users'])
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
            ->orderBy('position')
            ->get();

        return QueueEntryResource::collection($entries);
    }
}
