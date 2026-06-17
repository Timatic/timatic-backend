<?php

namespace App\Http\Resources;

use App\Models\Source as SourceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin SourceModel
 */
class Source extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'iconUrl' => SourceModel::iconUrlFor($this->id),
        ];
    }
}
