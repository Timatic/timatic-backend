<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackedDomainCreateRequest;
use App\Http\Resources;
use App\Models\ApiToken;
use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

class TrackedDomainController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\TrackedDomain', only: ['index']),
            new Middleware('can:create,App\Models\TrackedDomain', only: ['store']),
            new Middleware('can:delete,tracked_domain', only: ['destroy']),
        ];
    }

    /**
     * @param  User|ApiToken  $user
     */
    public function index(#[CurrentUser] $user): AnonymousResourceCollection
    {
        // An api token belongs to nobody: it owns no mappings and creates none
        $userId = $user instanceof User ? $user->id : null;

        $trackedDomains = QueryBuilder::for(TrackedDomain::query()->visibleTo($userId))
            ->allowedFilters([
                AllowedFilter::exact('domain'),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::scope('shared'),
            ])
            ->allowedIncludes([
                AllowedInclude::custom('customer', new IncludedRelationship),
                AllowedInclude::custom('budget', new IncludedRelationship),
            ])
            ->jsonPaginate();

        return Resources\TrackedDomain::collection($trackedDomains);
    }

    public function store(TrackedDomainCreateRequest $request): Resources\TrackedDomain
    {
        $trackedDomain = TrackedDomain::query()->create($request->validatedAttributes());

        return new Resources\TrackedDomain($trackedDomain);
    }

    public function destroy(TrackedDomain $trackedDomain, ResponseFactory $responseFactory): Response
    {
        $trackedDomain->delete();

        return $responseFactory->noContent();
    }
}
