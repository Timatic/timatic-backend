<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.settlement' => [Rule::in(['budget', 'internal', 'paid-per-hour'])],
            // Date filters - operator syntax
            'filter.startedAt.gt' => 'sometimes|date',
            'filter.startedAt.gte' => 'sometimes|date',
            'filter.startedAt.lt' => 'sometimes|date',
            'filter.startedAt.lte' => 'sometimes|date',
            'filter.endedAt.gt' => 'sometimes|date',
            'filter.endedAt.gte' => 'sometimes|date',
            'filter.endedAt.lt' => 'sometimes|date',
            'filter.endedAt.lte' => 'sometimes|date',
            // Text filters - contains operator
            'filter.userFullName.contains' => 'sometimes|string',
            'filter.ticketNumber.contains' => 'sometimes|string',
        ];
    }
}
