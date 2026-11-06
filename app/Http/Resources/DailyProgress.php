<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

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
            'date' => $this->getDate()->setTimezone(config('timatic.preferred_timezone'))->toDateString(),
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
