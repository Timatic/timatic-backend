<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission as PermissionModel;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin DerivedPermission|PermissionModel
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
        // Stored permissions are addressed by id, derived ones only have a name
        return $this->resource instanceof PermissionModel ? (string) $this->resource->id : $this->name;
    }

    public function toType(Request $request): string
    {
        return 'permissions';
    }
}
