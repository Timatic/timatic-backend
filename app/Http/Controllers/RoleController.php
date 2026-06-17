<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleCreateRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,Spatie\Permission\Models\Role', only: ['index']),
            new Middleware('can:create,Spatie\Permission\Models\Role', only: ['store']),
            new Middleware('can:update,role', only: ['update']),
        ];
    }

    public function index(): AnonymousResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedIncludes(['permissions'])
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
}
