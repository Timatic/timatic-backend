<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Http\Resources\Team as TeamResource;
use App\Models\Team;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class TeamController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Team', only: ['index']),
            new Middleware('can:view,team', only: ['show']),
            new Middleware('can:create,App\Models\Team', only: ['create', 'store']),
            new Middleware('can:update,team', only: ['edit', 'update']),
            new Middleware('can:delete,team', only: ['destroy']),
        ];
    }

    public function index(): JsonApiResourceCollection
    {
        return TeamResource::collection(Team::all());
    }

    public function store(TeamRequest $request): TeamResource
    {
        $validated = $request->validatedAttributes();

        $team = Team::query()->create($validated);

        return new TeamResource($team);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team);
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        $validated = $request->validatedAttributes();

        $team->update(array_filter($validated));

        return new TeamResource($team);
    }

    public function destroy(Team $team, ResponseFactory $responseFactory): Response
    {
        $team->delete();

        return $responseFactory->noContent();
    }
}
