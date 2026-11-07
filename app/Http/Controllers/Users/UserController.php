<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\User', only: ['index']),
            new Middleware('can:view,user', only: ['show']),
            new Middleware('can:create,App\Models\User', only: ['create', 'store']),
            new Middleware('can:update,user', only: ['edit', 'update']),
            new Middleware('can:delete,user', only: ['destroy']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::exact('externalId', 'external_id'),
            ])
            ->allowedIncludes([
                AllowedInclude::custom('team', new IncludedRelationship),
                AllowedInclude::custom('permissions', new IncludedRelationship),
                AllowedInclude::custom('roles', new IncludedRelationship),
            ])
            ->jsonPaginate();

        return Resources\User::collection($users);
    }

    public function store(UserRequest $request): Resources\User
    {
        $validated = $request->validatedAttributes();

        $user = User::query()->create($validated);

        $team = $request->relationships('team');
        if ($team !== null && isset($team['data']['id'])) {
            $user->team_id = (int) $team['data']['id'];
            $user->save();
        }

        $roleRelationships = $request->relationships('roles.data');
        if ($roleRelationships !== null) {
            // Spatie treats string ids as role names
            $user->syncRoles(array_map('intval', Arr::pluck($roleRelationships, 'id')));
        }

        $permissionRelationships = $request->relationships('permissions.data');
        if ($permissionRelationships !== null) {
            $user->syncPermissions(Arr::pluck($permissionRelationships, 'id'));
        }

        return new Resources\User($user->load(['team', 'roles', 'permissions']));
    }

    public function show(User $user): Resources\User
    {
        return new Resources\User($user->load('team'));
    }

    public function update(UserRequest $request, User $user): Resources\User
    {
        $validated = $request->validatedAttributes();

        $user->update(array_filter($validated));

        $team = $request->relationships('team');
        if ($team !== null && isset($team['data']['id'])) {
            $user->team_id = (int) $team['data']['id'];
            $user->save();
        }

        $roleRelationships = $request->relationships('roles.data');
        if ($roleRelationships !== null) {
            // Spatie treats string ids as role names
            $user->syncRoles(array_map('intval', Arr::pluck($roleRelationships, 'id')));
        }

        $permissionRelationships = $request->relationships('permissions.data');
        if ($permissionRelationships !== null) {
            $user->syncPermissions(Arr::pluck($permissionRelationships, 'id'));
        }

        return new Resources\User($user->load(['team', 'roles', 'permissions']));
    }

    public function destroy(User $user, ResponseFactory $responseFactory): Response
    {
        $user->delete();

        return $responseFactory->noContent();
    }
}
