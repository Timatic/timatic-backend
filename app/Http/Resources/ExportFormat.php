<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\DataTransferObjects\ExportFormat
 */
class ExportFormat extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'label' => $this->label,
            'periodOptions' => $this->periodOptions->value,
            'extension' => $this->extension,
        ];
    }

    public function toType(Request $request): string
    {
        return 'exportFormats';
    }

    public function toId(Request $request): string
    {
        return $this->key;
    }
}
