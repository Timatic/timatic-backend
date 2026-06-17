<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\User
 */
class User extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'externalId' => $this->external_id,
            'email' => $this->email,
            'givenName' => $this->given_name,
            'familyName' => $this->family_name,
            'isImpersonated' => $this->isImpersonated(),
            'impersonatedById' => $this->impersonatedById(),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'team' => Team::class,
    ];

    /**
     * @return array<string, callable>
     */
    public function toRelationships(Request $request): array
    {
        $relationships = [];

        // Add permissions relationship with derived permissions
        if ($this->resource && $this->relationLoaded('permissions')) {
            $relationships['permissions'] = function () {
                // Get all permissions (real + derived) as Permission resources
                $allPermissions = $this->getAllPermissions()->map(fn ($permission) => new DerivedPermission(
                    $permission->name,
                ));

                // Merge with derived permissions
                $combinedPermissions = $allPermissions->merge($this->derivedPermissions);

                // Convert to Permission resources
                return Permission::collection($combinedPermissions);
            };
        }

        return $relationships;
    }
}
