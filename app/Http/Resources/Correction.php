<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Correction
 */
class Correction extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
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
