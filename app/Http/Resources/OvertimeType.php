<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\OvertimeType
 */
class OvertimeType extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
