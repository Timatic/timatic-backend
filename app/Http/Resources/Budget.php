<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Budget
 */
class Budget extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        $activeVersion = $this->activeVersion();

        return [
            'budgetTypeId' => $this->budget_type_id,
            'customerId' => $this->customer_id,
            'showToCustomer' => $this->show_to_customer,
            'changeId' => $activeVersion->change_id,
            'contractId' => $activeVersion->contract_id,
            'title' => $activeVersion->title,
            'description' => $activeVersion->description,
            'totalPrice' => $activeVersion->total_price,
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'initialMinutes' => $activeVersion->initial_minutes,
            'isArchived' => $this->archived_at != null,
            'renewalFrequency' => $this->renewal_frequency,
            'createdAt' => $this->created_at,
            'updatedAt' => $activeVersion->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'entries' => Entry::class,
        'budgetType' => BudgetType::class,
        'customer' => Customer::class,
        'allowedUsers' => User::class,
        'supervisor' => User::class,
    ];

    /**
     * @return array<string, callable>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'currentPeriod' => function () {
                $period = $this->resource->getCurrentPeriodRelationData();

                return $period ? Period::make($period) : new MissingValue;
            },
            'lastPeriod' => function () {
                $period = $this->resource->getLastPeriodRelationData();

                return $period ? Period::make($period) : new MissingValue;
            },
        ];
    }
}
