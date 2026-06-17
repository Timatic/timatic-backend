<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\OptionalPatchParameters;
use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\Budget;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->addPatchOptionalValidation([
            'data.type' => ['required', 'in:events'],
            'data.attributes.sourceId' => ['required', 'string', 'exists:'.Source::class.',id'],
            'data.attributes.userId' => [
                'integer',
                'exists:'.User::class.',id',
                'required_without:data.attributes.userExternalId',
            ],
            'data.attributes.userExternalId' => [
                'string',
                'exists:'.User::class.',external_id',
                'required_without:data.attributes.userId',
            ],
            'data.attributes.budgetId' => ['integer', 'exists:'.Budget::class.',id'],
            'data.attributes.ticketId' => ['string'],
            'data.attributes.ticketNumber' => [],
            'data.attributes.ticketType' => [],
            'data.attributes.title' => [],
            'data.attributes.description' => [],
            'data.attributes.customerId' => ['integer', 'exists:customers,id'],
            'data.attributes.customerExternalId' => ['string'],
            'data.attributes.eventTypeId' => ['required', 'string'],
            'data.attributes.startedAt' => ['required_without:data.attributes.endedAt', 'date'],
            'data.attributes.endedAt' => ['required_without:data.attributes.startedAt', 'date'],
            'data.attributes.isInternal' => ['boolean'],
        ]);
    }
}
