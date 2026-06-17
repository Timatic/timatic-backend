<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\OptionalPatchParameters;
use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;

class BudgetUpdateRequest extends FormRequest
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
     * @return array<string,string>
     */
    public function rules(): array
    {
        $rules = [
            'data.type' => 'required|in:budgets',
            'data.attributes' => 'required|array',
            'data.attributes.title' => 'required',
            'data.attributes.initialMinutes' => 'required|numeric',
            'data.attributes.changeId' => '',
            'data.attributes.contractId' => '',
            'data.attributes.description' => '',
            'data.attributes.totalPrice' => 'required|numeric',
            'data.attributes.endedAt' => 'required|date',
            'data.attributes.supervisorUserId' => 'exists:users,id',
            'data.attributes.isArchived' => 'boolean',
            'data.attributes.showToCustomer' => 'boolean',
            'data.attributes.effectiveFrom' => 'date',
            'data.relationships.allowedUsers' => '',
            'data.relationships.allowedUsers.data' => 'array',
        ];

        return $this->addPatchOptionalValidation($rules, ['data.type', 'data.attributes']);
    }
}
