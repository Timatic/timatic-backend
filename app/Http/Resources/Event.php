<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Event
 */
class Event extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'userId' => $this->user_id,
            'budgetId' => $this->budget_id,
            'ticketId' => $this->ticket_id,
            'sourceId' => $this->source_id,
            'ticketNumber' => $this->ticket_number,
            'ticketType' => $this->ticket_type,
            'title' => $this->title,
            'description' => $this->description,
            'customerId' => $this->customer_id,
            'eventTypeId' => $this->event_type_id,
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'isInternal' => $this->is_internal,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'source' => Source::class,
    ];
}
