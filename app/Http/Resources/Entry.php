<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Entry
 */
class Entry extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'ticketId' => $this->ticket_id,
            'ticketNumber' => $this->ticket_number,
            'ticketTitle' => $this->ticket_title,
            'ticketType' => $this->ticket_type,
            'customerId' => $this->customer_id,
            'customerName' => $this->customer_name,
            'hourlyRate' => $this->getHourlyRateBigDecimal()->toFloat(),
            'hadEmergencyShift' => $this->when(
                ! is_null($this->had_emergency_shift),
                (bool) $this->had_emergency_shift
            ),
            'budgetId' => $this->budget_id,
            'isPaidPerHour' => $this->budget_id === null && $this->is_internal === false,
            'minutesSpent' => (int) $this->minutes_spent,
            'userId' => (string) $this->user_id,
            'userEmail' => $this->user_email,
            'userFullName' => $this->user_full_name,
            'createdByUserId' => $this->created_by_user_id,
            'createdByUserEmail' => $this->created_by_user_email,
            'createdByUserFullName' => $this->created_by_user_full_name,
            'entryType' => $this->entry_type,
            'description' => $this->description,
            'isInternal' => (bool) $this->is_internal,
            'startedAt' => $this->started_at,
            'endedAt' => $this->ended_at,
            'invoicedAt' => $this->invoiced_at,
            'isInvoiced' => $this->is_invoiced,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'isBasedOnSuggestion' => $this->entry_suggestion_id !== null,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public array $relationships = [
        'personalOvertime' => Overtime::class,
        'customerOvertime' => Overtime::class,
        'correctionEntryCorrection' => Correction::class,
        'correctedEntryCorrection' => Correction::class,
        'newEntryCorrection' => Correction::class,
        'customer' => Customer::class,
        'budget' => Budget::class,
    ];
}
