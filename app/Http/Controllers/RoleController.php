<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleCreateRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,Spatie\Permission\Models\Role', only: ['index']),
            new Middleware('can:view,role', only: ['show']),
            new Middleware('can:create,Spatie\Permission\Models\Role', only: ['store']),
            new Middleware('can:update,role', only: ['update']),
            new Middleware('can:delete,role', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonApiResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedIncludes(['permissions'])
            ->jsonPaginate();

        return Resources\Role::collection($roles);
    }

    public function store(RoleCreateRequest $request): Resources\Role
    {
        $validated = $request->validatedAttributes();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        // Handle permissions relationship if provided
        $permissions = $request->relationships('permissions');
        if ($permissions !== null && isset($permissions['data'])) {
            $permissionIds = array_map(fn ($p) => (int) $p['id'], $permissions['data']);
            $role->syncPermissions($permissionIds);
        }

        return new Resources\Role($role->load('permissions'));
    }

    public function show(Role $role): Resources\Role
    {
        return new Resources\Role($role);
    }

    public function update(RoleUpdateRequest $request, Role $role): Resources\Role
    {
        $validated = $request->validatedAttributes();

        $role->update(array_filter([
            'name' => $validated['name'] ?? null,
        ]));

        // Handle permissions relationship if provided
        $permissions = $request->relationships('permissions');
        if ($permissions !== null && isset($permissions['data'])) {
            $permissionIds = array_map(fn ($p) => (int) $p['id'], $permissions['data']);
            $role->syncPermissions($permissionIds);
        }

        return new Resources\Role($role->load('permissions'));
    }

    public function destroy(Role $role, ResponseFactory $responseFactory): Response
    {
        $role->delete();

        return $responseFactory->noContent();
    }
}
