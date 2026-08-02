<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\CompleteMatchRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function __construct(private readonly QueueService $queue) {}

    /**
     * POST /api/matches/{game}/complete
     *
     * Record the two team scores, mark the match completed, update stats and
     * rotate the queue according to the court's rule.
     */
    public function complete(Game $game, CompleteMatchRequest $request): JsonResponse
    {
        $match = $this->queue->completeMatch(
            $game,
            (int) $request->validated('score_a'),
            (int) $request->validated('score_b'),
        );

        return response()->json([
            'message' => 'Match completed.',
            'match' => new GameResource($match),
        ]);
    }
}
