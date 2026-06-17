<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntrySuggestionCollectionRequest extends FormRequest
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
            'filter.date.gt' => 'sometimes|date',
            'filter.date.gte' => 'sometimes|date',
            'filter.date.lt' => 'sometimes|date',
            'filter.date.lte' => 'sometimes|date',
        ];
    }
}
