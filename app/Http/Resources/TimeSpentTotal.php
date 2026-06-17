<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\TimeSpentTotal
 */
class TimeSpentTotal extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'internalMinutes' => $this->internalMinutes,
            'billableMinutes' => $this->billableMinutes,
            'periodUnit' => $this->periodUnit,
            'periodValue' => $this->periodValue,
        ];
    }

    public function toId(Request $request): string
    {
        return $this->resource->getId();
    }

    public function toType(Request $request)
    {
        return 'time-spent-totals';
    }
}
