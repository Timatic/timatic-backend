<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeSpentTotalsCollectionRequest;
use App\Http\Resources\TimeSpentTotal as TimeSpentTotalResource;
use App\Services\TimeSpentTotalsService;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetTimeSpentTotalsController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(
        TimeSpentTotalsCollectionRequest $request,
        TimeSpentTotalsService $totalsService,
        Repository $config,
    ): AnonymousResourceCollection {
        $unit = $request->input('periodUnit');
        $startedAtStart = CarbonImmutable::parse($request->input('filter.startedAt.gte'))
            ->setTimezone($config->get('timatic.preferred_timezone'))
            ->startOf($unit);
        $startedAtEnd = CarbonImmutable::parse($request->input('filter.startedAt.lte'))
            ->setTimezone($config->get('timatic.preferred_timezone'))
            ->endOf($unit);

        $teamId = $request->input('filter.teamId');
        $userId = $request->input('filter.userId');

        $periods = $totalsService->getTimeSpentTotalsPerPeriod(
            unit: $unit,
            startedAtStart: $startedAtStart,
            startedAtEnd: $startedAtEnd,
            teamId: $teamId,
            userId: $userId
        );

        return TimeSpentTotalResource::collection($periods)->additional([
            'meta' => [
                'totalInternalMinutes' => (int) $periods->sum('internalMinutes'),
                'totalBillableMinutes' => (int) $periods->sum('billableMinutes'),
            ],
        ]);
    }
}
