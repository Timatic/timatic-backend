<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\BudgetType
 */
class BudgetType extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        $renewalFrequencies = $this->renewal_frequencies;

        return [
            'title' => $this->title,
            'isArchived' => $this->is_archived,
            'hasChangeTicket' => $this->has_change_ticket,
            'renewalFrequencies' => $renewalFrequencies,
            'hasSupervisor' => $this->has_supervisor,
            'hasContractId' => $this->has_contract_id,
            'hasTotalPrice' => $this->has_total_price,
            'ticketIsRequired' => $this->ticket_is_required,
            'defaultTitle' => $this->default_title,
        ];
    }
}
