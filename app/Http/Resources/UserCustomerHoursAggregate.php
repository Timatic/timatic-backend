<?php

namespace App\Http\Resources;

use App\Models\UserCustomerHoursRecord;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin UserCustomerHoursRecord
 */
class UserCustomerHoursAggregate extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'customerId' => (string) $this->customer_id,
            'userId' => (string) $this->user_id,
            'internalMinutes' => (int) $this->internal_minutes,
            'budgetMinutes' => (int) $this->budget_minutes,
            'paidPerHourMinutes' => (int) $this->paid_per_hour_minutes,
        ];
    }

    public function toId(Request $request): string
    {
        return sha1((string) json_encode($this->resource));
    }

    public function toType(Request $request): string
    {
        return 'userCustomerHoursAggregates';
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'customer' => Customer::class,
        'user' => User::class,
    ];
}
