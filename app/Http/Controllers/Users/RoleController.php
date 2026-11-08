<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleCreateRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,Spatie\Permission\Models\Role', only: ['index']),
            new Middleware('can:create,Spatie\Permission\Models\Role', only: ['store']),
            new Middleware('can:update,role', only: ['update']),
            new Middleware('can:delete,role', only: ['destroy']),
        ];
    }

    public function index(): AnonymousResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedIncludes([
                AllowedInclude::custom('permissions', new IncludedRelationship),
            ])
            ->jsonPaginate();

        return Resources\Role::collection($roles);
    }

    public function store(RoleCreateRequest $request): Resources\Role
    {
        $validated = $request->validatedAttributes();

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $permissionRelationships = $request->relationships('permissions.data');
        if ($permissionRelationships !== null) {
            $role->syncPermissions(Arr::pluck($permissionRelationships, 'id'));
        }

        return new Resources\Role($role->load('permissions'));
    }

    public function update(RoleUpdateRequest $request, Role $role): Resources\Role
    {
        $role->update(array_filter($request->validatedAttributes()));

        $permissionRelationships = $request->relationships('permissions.data');
        if ($permissionRelationships !== null) {
            $role->syncPermissions(Arr::pluck($permissionRelationships, 'id'));
        }

        return new Resources\Role($role->load('permissions'));
    }

    public function destroy(Role $role, ResponseFactory $responseFactory): Response
    {
        abort_if($role->users()->exists(), 409, 'Cannot delete a role that is still assigned to users.');

        $role->delete();

        return $responseFactory->noContent();
    }
}
