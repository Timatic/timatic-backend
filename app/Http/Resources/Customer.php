<?php

namespace App\Http\Resources;

use App\Support\Auth\SharedBudgetViewer;
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
        $isSharedWithCustomer = $request->user() instanceof SharedBudgetViewer;

        return [
            'externalId' => $this->unless($isSharedWithCustomer, $this->external_id),
            'name' => $this->name,
            'hourlyRate' => $this->unless($isSharedWithCustomer, $this->hourly_rate ?? config('timatic.default_hourly_rate')),
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
