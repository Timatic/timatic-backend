<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketCollectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.customerId' => 'sometimes|nullable|string',
            'filter.search' => 'sometimes|string',
            'page.size' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
