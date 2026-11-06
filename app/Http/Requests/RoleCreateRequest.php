<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;

class RoleCreateRequest extends FormRequest
{
    use ValidatedAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'data.type' => ['required', 'string', 'in:roles'],
            'data.attributes.name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name',
            ],
            'data.relationships.permissions.data' => ['nullable', 'array'],
            'data.relationships.permissions.data.*.type' => ['string', 'in:permissions'],
            'data.relationships.permissions.data.*.id' => ['string', 'exists:permissions,name'],
        ];
    }
}
