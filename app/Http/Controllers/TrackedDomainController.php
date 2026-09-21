<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrackedDomainRequest;
use App\Http\Resources;
use App\Models\ApiToken;
use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class TrackedDomainController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\TrackedDomain', only: ['index']),
            new Middleware('can:create,App\Models\TrackedDomain', only: ['store']),
            new Middleware('can:update,tracked_domain', only: ['update']),
            new Middleware('can:delete,tracked_domain', only: ['destroy']),
        ];
    }

    /**
     * @param  User|ApiToken  $user
     */
    public function index(#[CurrentUser] $user): JsonApiResourceCollection
    {
        $trackedDomains = QueryBuilder::for(TrackedDomain::query()->visibleTo($user instanceof User ? $user->id : null))
            ->allowedFilters([
                AllowedFilter::exact('domain'),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::exact('isActive', 'is_active'),
            ])
            ->allowedIncludes([
                'customer',
                'budget',
            ])
            ->jsonPaginate();

        return Resources\TrackedDomain::collection($trackedDomains);
    }

    /**
     * @param  User|ApiToken  $user
     */
    public function store(TrackedDomainRequest $request, #[CurrentUser] $user): Resources\TrackedDomain
    {
        $attributes = $request->validatedAttributes();
        $userId = $user instanceof User ? $user->id : null;

        $trackedDomain = TrackedDomain::query()->create([
            ...Arr::except($attributes, 'is_private'),
            'user_id' => ($attributes['is_private'] ?? false) ? $userId : null,
            'created_by_user_id' => $userId,
        ]);

        return new Resources\TrackedDomain($trackedDomain);
    }

    public function update(TrackedDomainRequest $request, TrackedDomain $trackedDomain): Resources\TrackedDomain
    {
        $trackedDomain->update(Arr::except($request->validatedAttributes(), 'is_private'));

        return new Resources\TrackedDomain($trackedDomain);
    }

    public function destroy(TrackedDomain $trackedDomain, ResponseFactory $responseFactory): Response
    {
        $trackedDomain->delete();

        return $responseFactory->noContent();
    }
}
