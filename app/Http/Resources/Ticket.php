<?php

namespace App\Http\Resources;

use App\Models\Customer as CustomerModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @mixin \App\DataTransferObjects\Ticket
 */
class Ticket extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->number,
            'title' => $this->title,
            'createdAt' => $this->created_at->toISOString(),
            'closedAt' => $this->closed_at?->toISOString(),
            'url' => $this->url,
            'customerId' => $this->customer_id,
            'budgetId' => $this->budget_id,
        ];
    }

    public function toType(Request $request): string
    {
        return 'tickets';
    }

    public function toId(Request $request): string
    {
        return $this->id;
    }

    /**
     * @return array<string, \Closure(): (Customer|ResourceCollection|null)>
     */
    public function toCustomRelationships(Request $request): array
    {
        return [
            'customers' => fn () => ($customer = CustomerModel::query()->find($this->customer_id)) ? Customer::make($customer) : null,
            'actions' => fn () => TicketAction::collection($this->actions),
        ];
    }
}
