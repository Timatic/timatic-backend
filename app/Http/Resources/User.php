<?php

namespace App\Http\Resources;

use App\DataTransferObjects\DerivedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Collection;

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
    ];

    /**
     * @return array<string, mixed>
     */
    protected function resolveResourceObject(JsonApiRequest $request): array
    {
        $object = parent::resolveResourceObject($request);

        if ($this->resource && $this->relationLoaded('permissions') && in_array('permissions', $request->sparseIncluded() ?? [])) {
            $object['relationships']['permissions'] = [
                'data' => $this->buildCombinedPermissions()->map(fn ($p) => [
                    'id' => $p->name,
                    'type' => 'permissions',
                ])->values()->all(),
            ];
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        $with = parent::with($request);

        if (! ($request instanceof JsonApiRequest)) {
            return $with;
        }

        if (! $this->resource || ! $this->relationLoaded('permissions') || ! in_array('permissions', $request->sparseIncluded() ?? [])) {
            return $with;
        }

        $permIncluded = $this->buildCombinedPermissions()->map(fn ($p) => [
            'id' => $p->name,
            'type' => 'permissions',
            'attributes' => ['values' => $p->values],
        ])->values()->all();

        $with['included'] = array_merge($with['included'] ?? [], $permIncluded);

        return $with;
    }

    /**
     * @return Collection<int, DerivedPermission>
     */
    private function buildCombinedPermissions(): Collection
    {
        $allPermissions = $this->getAllPermissions()->map(fn ($permission) => new DerivedPermission($permission->name));

        return $allPermissions->merge($this->derivedPermissions);
    }
}
