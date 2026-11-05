<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

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

    public function index(Request $request): JsonApiResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::exact('externalId', 'external_id'),
            ])
            ->allowedIncludes([
                'team',
                'permissions',
                'roles',
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

        // Handle roles relationship if provided
        $roles = $request->relationships('roles');
        if ($roles !== null && isset($roles['data'])) {
            $roleIds = array_map(fn ($r) => (int) $r['id'], $roles['data']);
            $user->syncRoles($roleIds);
        }

        // Handle permissions relationship if provided
        $permissions = $request->relationships('permissions');
        if ($permissions !== null && isset($permissions['data'])) {
            $permissionIds = array_map(fn ($p) => (int) $p['id'], $permissions['data']);
            $user->syncPermissions($permissionIds);
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

        // Handle roles relationship if provided
        $roles = $request->relationships('roles');
        if ($roles !== null && isset($roles['data'])) {
            $roleIds = array_map(fn ($r) => (int) $r['id'], $roles['data']);
            $user->syncRoles($roleIds);
        }

        // Handle permissions relationship if provided
        $permissions = $request->relationships('permissions');
        if ($permissions !== null && isset($permissions['data'])) {
            $permissionIds = array_map(fn ($p) => (int) $p['id'], $permissions['data']);
            $user->syncPermissions($permissionIds);
        }

        return new Resources\User($user->load(['team', 'roles', 'permissions']));
    }

    public function destroy(User $user, ResponseFactory $responseFactory): Response
    {
        $user->delete();

        return $responseFactory->noContent();
    }
}
