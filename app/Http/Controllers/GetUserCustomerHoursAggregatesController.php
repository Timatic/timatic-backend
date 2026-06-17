<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserCustomerHoursAggregatesCollectionRequest;
use App\Http\Resources\UserCustomerHoursAggregate as UserCustomerHoursAggregateResource;
use App\Models\UserCustomerHoursRecord;
use App\QueryFilters\DateFilterCallback;
use App\Services\UserCustomerHoursAggregateService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GetUserCustomerHoursAggregatesController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(
        DatabaseManager $db,
        UserCustomerHoursAggregatesCollectionRequest $request,
        UserCustomerHoursAggregateService $aggregateService,
    ): AnonymousResourceCollection {
        $query = QueryBuilder::for(UserCustomerHoursRecord::class)
            ->allowedFilters([
                AllowedFilter::callback('startedAt', DateFilterCallback::make('started_at')),
                AllowedFilter::callback('endedAt', DateFilterCallback::make('ended_at')),
                AllowedFilter::exact('teamId', 'user_team_id'),
                AllowedFilter::exact('userId', 'user_id'),
            ])
            ->allowedIncludes([
                'customer',
                'user',
                'user.team',
            ])
            ->getEloquentBuilder();

        $results = $aggregateService->paginatedAggregates($query);

        return UserCustomerHoursAggregateResource::collection($results)->additional([
            'meta' => [
                'totalInternalMinutes' => $aggregateService->totalInternalMinutes($query),
                'totalBudgetMinutes' => $aggregateService->totalBudgetMinutes($query),
                'totalPaidPerHourMinutes' => $aggregateService->totalPaidPerHourMinutes($query),
            ],
        ]);
    }
}
