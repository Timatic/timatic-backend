<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
     * @return array<string, class-string|callable>
     */
    public function toRelationships(Request $request): array
    {
        return array_merge($this->relationships, [
            'permissions' => fn () => new EloquentCollection(
                $this->getAllPermissions()
                    ->map(fn ($permission) => new DerivedPermission($permission->name))
                    ->merge($this->derivedPermissions)
                    ->all()
            ),
        ]);
    }
}
