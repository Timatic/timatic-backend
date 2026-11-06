<?php

namespace App\Http\Requests;

use App\Http\Concerns\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;

class PermissionCreateRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:permissions'],
            'data.attributes.name' => [
                'required',
                'string',
                'max:255',
                'unique:permissions,name',
            ],
        ];
    }
}
