<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @mixin \App\Models\User
 */
class User extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
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
        'roles' => Role::class,
    ];

    /**
     * @return array<string, \Closure(): ResourceCollection>
     */
    public function toCustomRelationships(Request $request): array
    {
        return [
            'permissions' => fn () => Permission::collection(
                $this->getAllPermissions()
                    ->map(fn ($permission) => new DerivedPermission($permission->name))
                    ->merge($this->derivedPermissions)
            ),
        ];
    }
}
