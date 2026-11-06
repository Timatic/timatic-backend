<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Exception;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin DerivedPermission|\Spatie\Permission\Models\Permission
 */
class Permission extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'values' => property_exists($this->resource, 'values') ? $this->resource->values : null,
        ];
    }

    /**
     * @throws Exception
     */
    public function toId(Request $request): string
    {
        return $this->name;
    }

    public function toType(Request $request): string
    {
        return 'permissions';
    }
}
