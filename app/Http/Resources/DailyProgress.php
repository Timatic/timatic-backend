<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\DailyProgress
 */
class DailyProgress extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'userId' => $this->getUserId(),
            'date' => (function () {
                $date = $this->getDate()->tz(config('timatic.preferred_timezone'));
                assert($date instanceof Carbon);

                return $date->toDateString();
            })(),
            'progress' => $this->getProgress(),
        ];
    }

    public function toId(Request $request): string
    {
        return $this->resource->getId();
    }

    public function toType(Request $request): string
    {
        return 'dailyProgress';
    }
}
