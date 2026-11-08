<?php

namespace App\Http\Resources;

use App\Support\Auth\SharedBudgetViewer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin \App\Models\Period
 */
class Period extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $tags = ['budget.'.$this->budget->id];
        $cacheKey = 'period.'.$this->resource->getId();

        $attributes = Cache::tags($tags)->remember($cacheKey, 60 * 60 * 24, fn (): array => [
            'budgetTitle' => $this->getTitle(),
            'budgetDescription' => $this->getDescription(),
            'startDate' => $this->getStartDate(),
            'endDate' => $this->getEndDate(),
            'initialMinutes' => $this->getInitialMinutes(),
            'remainingMinutes' => $this->getRemainingMinutes(true),
            'spentMinutes' => $this->getSpentMinutes(),
            'ticketCount' => $this->getTicketCount(),
            'totalPrice' => $this->getTotalPrice(),
        ]);

        // The cache is shared with logged in users, so the price is left out after reading it
        return $request->user() instanceof SharedBudgetViewer ? Arr::except($attributes, 'totalPrice') : $attributes;
    }

    public function toId(Request $request): string
    {
        return $this->resource->getId();
    }

    public function toType(Request $request): string
    {
        return 'periods';
    }
}
