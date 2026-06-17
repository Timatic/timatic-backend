<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Correction
 */
class Correction extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'correctedEntry' => Entry::class,
        'correctionEntry' => Entry::class,
        'newEntry' => Entry::class,
    ];
}
