<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionCreateRequest;
use App\Http\Requests\PermissionUpdateRequest;
use App\Http\Resources;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,Spatie\Permission\Models\Permission', only: ['index']),
            new Middleware('can:view,permission', only: ['show']),
            new Middleware('can:create,Spatie\Permission\Models\Permission', only: ['store']),
            new Middleware('can:update,permission', only: ['update']),
            new Middleware('can:delete,permission', only: ['destroy']),
        ];
    }

    /**
     * @return \TiMacDonald\JsonApi\JsonApiResourceCollection<Resources\Permission>
     */
    public function index(Request $request): JsonApiResourceCollection
    {
        $permissions = QueryBuilder::for(Permission::class)
            ->where('guard_name', 'web')
            ->jsonPaginate();

        return Resources\Permission::collection($permissions);
    }

    public function store(PermissionCreateRequest $request): Resources\Permission
    {
        $validated = $request->validatedAttributes();

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        return new Resources\Permission($permission);
    }

    public function show(Permission $permission): Resources\Permission
    {
        return new Resources\Permission($permission);
    }

    public function update(PermissionUpdateRequest $request, Permission $permission): Resources\Permission
    {
        $validated = $request->validatedAttributes();

        $permission->update(array_filter([
            'name' => $validated['name'] ?? null,
        ]));

        return new Resources\Permission($permission);
    }

    public function destroy(Permission $permission, ResponseFactory $responseFactory): Response
    {
        $permission->delete();

        return $responseFactory->noContent();
    }
}
