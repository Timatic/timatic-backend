<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;

class BudgetCreateRequest extends FormRequest
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
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'data.type' => 'required|in:budgets',
            'data.attributes.title' => [
                'required',
                function ($attribute, $value, $fail) {
                    $budgets = Budget::query()
                        ->isArchived(false)
                        ->where('customer_id', $this->input('data.attributes.customerId'))
                        ->get();

                    foreach ($budgets as $budget) {
                        if ($budget->activeVersion()->title == $value) {
                            $fail('The title of a budget should be unique.');
                        }
                    }
                },
            ],
            'data.attributes.initialMinutes' => 'required|numeric',
            'data.attributes.changeId' => '',
            'data.attributes.contractId' => '',
            'data.attributes.description' => '',
            'data.attributes.totalPrice' => 'required|numeric',
            'data.attributes.startedAt' => 'required|date',
            'data.attributes.endedAt' => 'required|date',
            'data.attributes.customerId' => 'required|integer|exists:customers,id',
            'data.attributes.showToCustomer' => 'boolean',
            'data.attributes.budgetTypeId' => 'required|exists:budget_types,id',
            'data.attributes.supervisorUserId' => 'exists:users,id',
            'data.attributes.renewalFrequency' => 'in:monthly,yearly',
            'data.relationships.allowedUsers' => '',
            'data.relationships.allowedUsers.data' => 'array',
        ];
    }
}
