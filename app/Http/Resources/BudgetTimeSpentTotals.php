<?php

namespace App\Http\Resources;

use App\DataTransferObjects\BudgetTimeSpentTotal;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin BudgetTimeSpentTotal
 */
class BudgetTimeSpentTotals extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'remainingMinutes' => $this->remainingMinutes,
            'periodUnit' => $this->periodUnit,
            'periodValue' => $this->periodValue,
        ];
    }

    public function toId(Request $request): string
    {
        return $this->resource->getId();
    }

    public function toType(Request $request): string
    {
        return 'budget-time-spent-totals';
    }
}
