<?php

namespace App\Http\Requests;

use App\Http\Concerns\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleUpdateRequest extends FormRequest
{
    use ValidatedAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleId = $this->route('role') instanceof Role
            ? $this->route('role')->id
            : null;

        return [
            'data.type' => ['required', 'string', 'in:roles'],
            'data.id' => ['sometimes', 'string', $this->matchesRouteResourceId()],
            'data.attributes.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($roleId),
            ],
            'data.relationships.permissions.data' => ['nullable', 'array'],
            'data.relationships.permissions.data.*.type' => ['string', 'in:permissions'],
            'data.relationships.permissions.data.*.id' => ['string', 'exists:permissions,name'],
        ];
    }
}
