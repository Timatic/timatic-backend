<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Customer
 */
class Customer extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'externalId' => $this->external_id,
            'name' => $this->name,
            'hourlyRate' => $this->hourly_rate ?? config('timatic.default_hourly_rate'),
            'isOwnOrganization' => $this->is_own_organization,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'accountManager' => User::class,
    ];
}
