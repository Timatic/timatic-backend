<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrackedDomainCreateRequest;
use App\Http\Resources;
use App\Models\ApiToken;
use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
            new Middleware('can:delete,tracked_domain', only: ['destroy']),
        ];
    }

    /**
     * @param  User|ApiToken  $user
     */
    public function index(#[CurrentUser] $user): JsonApiResourceCollection
    {
        $trackedDomains = QueryBuilder::for(TrackedDomain::query()->visibleTo($this->userId($user)))
            ->allowedFilters([
                AllowedFilter::exact('domain'),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::scope('shared'),
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
    public function store(TrackedDomainCreateRequest $request, #[CurrentUser] $user): Resources\TrackedDomain
    {
        $trackedDomain = TrackedDomain::query()->create([
            ...$request->validatedAttributes(),
            'created_by_user_id' => $this->userId($user),
        ]);

        return new Resources\TrackedDomain($trackedDomain);
    }

    public function destroy(TrackedDomain $trackedDomain, ResponseFactory $responseFactory): Response
    {
        $trackedDomain->delete();

        return $responseFactory->noContent();
    }

    /** Null for an api token that belongs to nobody: it owns no mappings and creates none. */
    private function userId(User|ApiToken $user): ?int
    {
        return $user instanceof User ? $user->id : null;
    }
}
