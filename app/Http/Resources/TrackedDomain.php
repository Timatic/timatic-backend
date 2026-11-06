<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\TrackedDomain
 */
class TrackedDomain extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'trackedDomains';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'domain' => $this->domain,
            'path' => $this->path,
            'customerId' => (string) $this->customer_id,
            'budgetId' => $this->budget_id,
            'isInternal' => $this->is_internal,
            'userId' => $this->user_id === null ? null : (string) $this->user_id,
            'parentId' => $this->parent_id,
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
