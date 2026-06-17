<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\EntrySuggestion
 */
class EntrySuggestion extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'entrySuggestions';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'ticketId' => $this->ticket_id,
            'ticketNumber' => $this->ticket_number,
            'customerId' => $this->customer_id,
            'userId' => $this->user_id,
            'date' => $this->date,
            'ticketTitle' => $this->ticket_title,
            'ticketType' => $this->ticket_type,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'budgetId' => $this->budget_id,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'activities' => Activity::class,
    ];
}
