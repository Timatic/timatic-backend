<?php

namespace App\Http\Controllers;

use App\Http\Resources\Period as PeriodResource;
use App\Models\Budget;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetBudgetPeriodsController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Budget $budget): AnonymousResourceCollection
    {
        return PeriodResource::collection($budget->periods());
    }
}
