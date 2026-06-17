<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetTimeSpentTotalsCollectionRequest;
use App\Http\Resources\BudgetTimeSpentTotals as BudgetTimeSpentTotalsResource;
use App\Services\TimeSpentTotalsService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetBudgetTimeSpentTotalsController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(
        BudgetTimeSpentTotalsCollectionRequest $request,
        TimeSpentTotalsService $totalsService,
        Repository $config
    ): AnonymousResourceCollection {
        $unit = $request->input('periodUnit');
        $budgetId = $request->input('filter.budgetId');

        $budget = $totalsService->getTimeSpentTotalsPerBudget(
            unit: $unit,
            budgetId: $budgetId
        );

        return BudgetTimeSpentTotalsResource::collection($budget);
    }
}
