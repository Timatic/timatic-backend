<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionCreateRequest;
use App\Http\Resources;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,Spatie\Permission\Models\Permission', only: ['index']),
            new Middleware('can:create,Spatie\Permission\Models\Permission', only: ['store']),
        ];
    }

    public function index(): AnonymousResourceCollection
    {
        $permissions = QueryBuilder::for(Permission::class)
            ->where('guard_name', 'web')
            ->jsonPaginate();

        return Resources\Permission::collection($permissions);
    }

    public function store(PermissionCreateRequest $request): Resources\Permission
    {
        $validated = $request->validatedAttributes();

        $permission = Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        return new Resources\Permission($permission);
    }
}
