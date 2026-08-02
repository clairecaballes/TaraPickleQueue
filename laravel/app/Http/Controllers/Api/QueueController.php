<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\JoinQueueRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\QueueEntryResource;
use App\Models\Court;
use App\Models\Group;
use App\Models\QueueEntry;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queue) {}

    /**
     * GET /api/courts/{court}/queue
     *
     * The current line for a court: waiting entries (ordered) then called/on-court.
     */
    public function index(Court $court): AnonymousResourceCollection
    {
        $entries = QueueEntry::where('court_id', $court->id)
            ->with(['court', 'user', 'group.users'])
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
            ->orderBy('position')
            ->get();

        return QueueEntryResource::collection($entries);
    }

    /**
     * POST /api/courts/{court}/queue
     *
     * Join the line as a solo player or as a group (group_id).
     */
    public function store(Court $court, JoinQueueRequest $request): JsonResponse
    {
        $groupId = $request->validated('group_id');
        $group = $groupId ? Group::find($groupId) : null;

        // Guard against the group vanishing between validation and lookup.
        if ($groupId && ! $group) {
            throw ValidationException::withMessages([
                'group_id' => ['The selected group is invalid.'],
            ]);
        }

        $entry = $this->queue->join($court, $request->user(), $group);

        return response()->json([
            'message' => 'Joined the queue.',
            'queue_entry' => new QueueEntryResource($entry->load(['court', 'user', 'group.users'])),
        ], 201);
    }

    /**
     * DELETE /api/queue/{queueEntry}
     *
     * Leave the line (waiting/called entries only).
     */
    public function destroy(QueueEntry $queueEntry): JsonResponse
    {
        $this->queue->leave($queueEntry);

        return response()->json(['message' => 'Left the queue.']);
    }

    /**
     * POST /api/queue/{queueEntry}/skip
     *
     * Manually skip a waiting/called entry.
     */
    public function skip(QueueEntry $queueEntry): JsonResponse
    {
        $this->queue->skip($queueEntry);

        return response()->json(['message' => 'Queue entry skipped.']);
    }

    /**
     * POST /api/courts/{court}/next-up
     *
     * Call the next units to the court (FIFO, or skill-grouped when the court
     * has skill_grouping enabled).
     */
    public function callNext(Court $court): JsonResponse
    {
        $called = $this->queue->callNextUp($court);

        return response()->json([
            'message' => $called ? 'Players called to court.' : 'Not enough players waiting to fill the court.',
            'called' => QueueEntryResource::collection($called),
        ]);
    }

    /**
     * POST /api/courts/{court}/confirm-call
     *
     * Confirm the pending call, start the match and split players into teams.
     */
    public function confirmCall(Court $court): JsonResponse
    {
        $match = $this->queue->confirmCall($court);

        return response()->json([
            'message' => 'Players confirmed. Match started.',
            'match' => new GameResource($match),
        ], 201);
    }
}
