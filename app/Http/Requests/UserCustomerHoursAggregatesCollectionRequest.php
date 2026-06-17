<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserCustomerHoursAggregatesCollectionRequest extends FormRequest
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
            // Date filters - operator syntax
            'filter.startedAt.gt' => 'sometimes|date',
            'filter.startedAt.gte' => 'sometimes|date',
            'filter.startedAt.lt' => 'sometimes|date',
            'filter.startedAt.lte' => 'sometimes|date',
            'filter.endedAt.gt' => 'sometimes|date',
            'filter.endedAt.gte' => 'sometimes|date',
            'filter.endedAt.lt' => 'sometimes|date',
            'filter.endedAt.lte' => 'sometimes|date',
        ];
    }
}
