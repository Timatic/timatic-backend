<?php

namespace App\Http\Resources;

use App\Support\Auth\SharedBudgetViewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin \App\Models\Budget
 */
class Budget extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $activeVersion = $this->activeVersion();
        $isSharedWithCustomer = $request->user() instanceof SharedBudgetViewer;

        return [
            'budgetTypeId' => $this->budget_type_id,
            'customerId' => (string) $this->customer_id,
            'showToCustomer' => $this->show_to_customer,
            'changeId' => $this->unless($isSharedWithCustomer, $activeVersion->change_id),
            'contractId' => $this->unless($isSharedWithCustomer, $activeVersion->contract_id),
            'title' => $activeVersion->title,
            'description' => $activeVersion->description,
            'totalPrice' => $this->unless($isSharedWithCustomer, (float) $activeVersion->total_price),
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'initialMinutes' => $activeVersion->initial_minutes,
            'isArchived' => $this->archived_at != null,
            'isShared' => $this->share_token_hash !== null,
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
     * @return array<string, \Closure(): (Period|MissingValue)>
     */
    public function toCustomRelationships(Request $request): array
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
