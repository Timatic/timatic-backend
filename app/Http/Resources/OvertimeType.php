<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\OvertimeType
 */
class OvertimeType extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'overtimeTypes';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
