<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Customer
 */
class Customer extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'externalId' => $this->external_id,
            'name' => $this->name,
            'color' => $this->color,
            'hourlyRate' => $this->hourly_rate,
            'isOwnOrganization' => $this->is_own_organization,
            'trackedDomainsCount' => $this->whenCounted('trackedDomains'),
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
