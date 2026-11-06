<?php

namespace App\Http\Requests;

use App\Http\Concerns\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;

class TeamRequest extends FormRequest
{
    use ValidatedAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'data.type' => ['required', 'string', 'in:teams'],
            'data.attributes.externalId' => ['string'],
            'data.attributes.name' => ['required', 'string'],
        ];
    }
}
