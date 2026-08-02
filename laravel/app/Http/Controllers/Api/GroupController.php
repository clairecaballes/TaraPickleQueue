<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupController extends Controller
{
    /**
     * GET /api/groups
     *
     * The authenticated user's squads (2 or 4 players), used when joining the
     * queue as a unit.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $groups = $request->user()
            ->groups()
            ->with('users')
            ->orderBy('id')
            ->get();

        return GroupResource::collection($groups);
    }
}
