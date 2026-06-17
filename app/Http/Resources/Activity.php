<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Activity
 */
class Activity extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'sourceId' => $this->source_id,
            'eventTypeId' => $this->event_type_id,
            'customerId' => $this->customer_id,
            'ticketId' => $this->ticket_id,
            'entrySuggestionId' => $this->entry_suggestion_id,
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'title' => $this->title,
            'description' => $this->description,
            'isInternal' => $this->is_internal,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'events' => Event::class,
    ];
}
