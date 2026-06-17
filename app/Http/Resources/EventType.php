<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\EventType
 */
class EventType extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'eventTypes';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
