<?php

namespace App\Http\Resources;

use App\Models\Source as SourceModel;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin SourceModel
 */
class Source extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'iconUrl' => SourceModel::iconUrlFor($this->id),
        ];
    }
}
