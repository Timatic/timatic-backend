<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\OptionalPatchParameters;
use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryUpdateRequest extends FormRequest
{
    use OptionalPatchParameters;
    use ValidatedAttributes;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string|array<Rule|string>>
     */
    public function rules(): array
    {
        return $this->addPatchOptionalValidation([
            'data.type' => 'required|in:entries',
            'data.attributes.isPaidPerHour' => [
                Rule::requiredIf(
                    fn () => is_null($this->input('data.attributes.budgetId')) && $this->input('data.attributes.isInternal') == false
                ),
                'boolean',
            ],
            'data.attributes.entrySuggestionId' => 'integer',
            'data.attributes.ticketId' => 'string',
            'data.attributes.ticketType' => 'string',
            'data.attributes.ticketTitle' => 'string',
            'data.attributes.ticketNumber' => 'string',
            'data.attributes.customerId' => 'required|integer|exists:customers,id',
            'data.attributes.customerName' => 'string',
            'data.attributes.entryType' => 'required|in:regular,correction,closing_budget_to_profit',
            'data.attributes.budgetId' => 'required_without:data.attributes.isInternal|integer|exists:budgets,id',
            'data.attributes.description' => 'string',
            'data.attributes.hasOvertime' => 'required|boolean',
            'data.attributes.hasCustomerOvertime' => 'required|boolean',
            'data.attributes.isInternal' => 'required_without:data.attributes.budgetId|boolean',
            'data.attributes.overtimeStartedAt' => 'date',
            'data.attributes.overtimeEndedAt' => 'date',
            'data.attributes.startedAt' => 'required|date',
            'data.attributes.endedAt' => 'required|date',
            'data.attributes.isInvoiced' => 'bool|in:true,1',
        ]);
    }
}
