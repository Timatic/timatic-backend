<?php

namespace App\Http\Controllers;

use App\Http\Resources\Period as PeriodResource;
use App\Models\Budget;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class GetBudgetPeriodsController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Budget $budget): JsonApiResourceCollection
    {
        return PeriodResource::collection($budget->periods());
    }
}
