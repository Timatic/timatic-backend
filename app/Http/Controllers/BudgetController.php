<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetCreateRequest;
use App\Http\Requests\BudgetUpdateRequest;
use App\Http\Resources\Budget as BudgetResource;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\QueryBuilder\CustomRelationInclude;
use App\Services\BudgetVersionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class BudgetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Budget', only: ['index']),
            new Middleware('can:view,budget', only: ['show']),
            new Middleware('can:create,App\Models\Budget', only: ['create', 'store']),
            new Middleware('can:update,budget', only: ['edit', 'update']),
            new Middleware('can:delete,budget', only: ['destroy']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $budgets = QueryBuilder::for(Budget::class)
            ->allowedFilters([
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::exact('budgetTypeId', 'budget_type_id'),
                AllowedFilter::scope('isArchived'),
                AllowedFilter::scope('customerExternalId', 'externalId'),
                AllowedFilter::scope('showToCustomer'),
            ])
            ->allowedIncludes([
                'customer',
                'allowedUsers',
                'supervisor',
                AllowedInclude::custom('lastPeriod', new CustomRelationInclude),
                AllowedInclude::custom('currentPeriod', new CustomRelationInclude),
            ])
            ->jsonPaginate();

        return BudgetResource::collection($budgets);
    }

    public function store(
        BudgetCreateRequest $request,
        BudgetVersionService $versionService,
        DatabaseManager $db
    ): JsonResponse {
        $attributes = $request->validatedAttributes();

        $userRelationships = $request->relationships('allowedUsers.data');

        $budget = $db->transaction(function () use ($versionService, $attributes, $userRelationships) {
            /** @var Budget $budget */
            $budget = Budget::query()->create($attributes);

            if ($userRelationships !== null) {
                $budget->allowedUsers()->sync(Arr::pluck($userRelationships, 'id'));
            }

            $versionService->createAndReplaceVersion($budget, $attributes);

            return $budget;
        });

        $budget->load('allowedUsers');

        return (new BudgetResource($budget))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Budget $budget): BudgetResource
    {
        $budget->load('allowedUsers', 'customer');

        return new BudgetResource($budget);
    }

    /**
     * @throws Throwable
     */
    public function update(
        BudgetUpdateRequest $request,
        Budget $budget,
        BudgetVersionService $versionService,
        DatabaseManager $db
    ): BudgetResource {
        $attributes = $request->validatedAttributes();

        $userRelationships = $request->relationships('allowedUsers.data');

        $budget = $db->transaction(function () use ($budget, $versionService, $attributes, $userRelationships) {
            if (array_key_exists('is_archived', $attributes)) {
                $attributes['archived_at'] = $attributes['is_archived'] ? Carbon::now() : null;
            }
            $budget->update($attributes);

            if ($userRelationships !== null) {
                $budget->allowedUsers()->sync(Arr::pluck($userRelationships, 'id'));
            }

            if (array_key_exists('is_archived', $attributes)) {
                // don't update the version when the budget is being archived
                return $budget;
            }

            $budgetVersionFields = (new BudgetVersion)->getFillable();
            if (collect($attributes)->hasAny($budgetVersionFields)) {
                $versionService->createAndReplaceVersion($budget, $attributes);
            }

            return $budget;
        });

        $budget->load('allowedUsers');

        return new BudgetResource($budget);
    }

    /**
     * @throws Exception
     */
    public function destroy(Budget $budget): Response
    {
        $budget->delete();

        return response()->noContent(200);
    }
}
