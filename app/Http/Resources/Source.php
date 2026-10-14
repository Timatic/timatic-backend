<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Source
 */
class Source extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'iconUrl' => \App\Models\Source::iconUrlFor($this->id),
        ];
    }
}
