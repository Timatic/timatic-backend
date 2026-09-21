<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\TrackedDomain
 */
class TrackedDomain extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'domain' => $this->domain,
            'path' => $this->path,
            'customerId' => $this->customer_id,
            'budgetId' => $this->budget_id,
            'isInternal' => $this->is_internal,
            'isActive' => $this->is_active,
            'isPrivate' => $this->user_id !== null,
            'createdByUserId' => $this->created_by_user_id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'customer' => Customer::class,
        'budget' => Budget::class,
    ];
}
