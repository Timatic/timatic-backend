<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \Spatie\Permission\Models\Role
 */
class Role extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'permissions' => Permission::class,
    ];
}
