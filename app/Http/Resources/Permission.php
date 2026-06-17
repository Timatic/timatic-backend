<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin DerivedPermission
 */
class Permission extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'values' => $this->values,
        ];
    }

    /**
     * @throws Exception
     */
    public function toId(Request $request): string
    {
        return (string) $this->resource->getAttribute('name');
    }

    public function toType(Request $request): string
    {
        return 'permissions';
    }
}
