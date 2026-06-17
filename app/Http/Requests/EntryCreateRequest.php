<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryCreateRequest extends FormRequest
{
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
        return [
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
            'data.attributes.entryType' => 'required|in:regular,correction,custom,closing_budget_to_profit',
            'data.attributes.budgetId' => [
                'required_without:data.attributes.isInternal',
                'integer',
                Rule::exists('budgets', 'id')->where(function ($query) {
                    return $query
                        ->where('id', $this->input('data.attributes.budgetId'))
                        ->where('started_at', '<=', $this->input('data.attributes.startedAt'))
                        ->where('ended_at', '>=', $this->input('data.attributes.endedAt'));
                }),
            ],
            'data.attributes.userId' => 'integer|exists:users,id',
            'data.attributes.userFullName' => 'string',
            'data.attributes.userEmail' => 'email|string',
            'data.attributes.description' => 'string',
            'data.attributes.hasOvertime' => 'required|boolean',
            'data.attributes.hasCustomerOvertime' => 'required|boolean',
            'data.attributes.isInternal' => 'required_without:data.attributes.budgetId|boolean',
            'data.attributes.overtimeStartedAt' => 'date',
            'data.attributes.overtimeEndedAt' => 'date',
            'data.attributes.startedAt' => 'required|date',
            'data.attributes.endedAt' => 'required|date',
            'data.attributes.isInvoiced' => 'bool|in:true,1',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'data.attributes.budgetId.exists' => 'This budget isn\'t available at the selected date and time.',
        ];
    }
}
