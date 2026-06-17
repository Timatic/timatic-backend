<?php

namespace App\Http\Resources;

use App\Models\Customer as CustomerModel;
use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\DataTransferObjects\Ticket
 */
class Ticket extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->number,
            'title' => $this->title,
            'createdAt' => $this->created_at->toISOString(),
            'closedAt' => $this->closed_at?->toISOString(),
            'url' => $this->url,
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

    public function toRelationships(Request $request): array
    {
        return [
            'customers' => fn () => Customer::make(
                $this->customer_id ? CustomerModel::find($this->customer_id) : null
            ),
            'actions' => fn () => TicketAction::collection($this->actions),
        ];
    }
}
