<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Team
 */
class Team extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'externalId' => $this->external_id,
            'name' => $this->name,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
