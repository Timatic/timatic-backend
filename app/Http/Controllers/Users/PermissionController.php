<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionCreateRequest;
use App\Http\Resources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;
use Spatie\QueryBuilder\QueryBuilder;

class PermissionController extends Controller implements HasMiddleware
{
    /** The actions Filament Shield prefixes its generated permissions with, which nothing checks */
    private const array FILAMENT_SHIELD_PREFIXES = [
        'view-any', 'view', 'create', 'update', 'delete', 'delete-any', 'restore', 'restore-any',
        'force-delete', 'force-delete-any', 'replicate', 'reorder',
    ];

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
            ->where(function (Builder $query): void {
                foreach (self::FILAMENT_SHIELD_PREFIXES as $prefix) {
                    $query->whereNot('name', 'like', $prefix.'.%');
                }
            })
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
