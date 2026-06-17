<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
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

        $cachedPeriod = Cache::tags($tags)->get($cacheKey);

        if ($cachedPeriod !== null) {
            return $cachedPeriod;
        }

        $attributes = [
            'budgetTitle' => $this->getTitle(),
            'budgetDescription' => $this->getDescription(),
            'startDate' => $this->getStartDate(),
            'endDate' => $this->getEndDate(),
            'initialMinutes' => $this->getInitialMinutes(),
            'remainingMinutes' => $this->getRemainingMinutes(true),
            'spentMinutes' => $this->getSpentMinutes(),
            'ticketCount' => $this->getTicketCount(),
            'totalPrice' => $this->getTotalPrice(),
        ];
        Cache::tags($tags)->put($cacheKey, $attributes, 60 * 60 * 24);

        return $attributes;
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
