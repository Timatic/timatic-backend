<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Webmozart\Assert\Assert;

class RoleUpdateRequest extends FormRequest
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
        $role = $this->route('role');

        Assert::isInstanceOf($role, Role::class);

        return [
            'data.type' => ['required', 'string', 'in:roles'],
            'data.attributes.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id),
            ],
            // Permissions relationship (optional, for syncing)
            'data.relationships.permissions.data' => ['nullable', 'array'],
            'data.relationships.permissions.data.*.type' => ['string', 'in:permissions'],
            'data.relationships.permissions.data.*.id' => ['string', 'exists:permissions,id'],
        ];
    }
}
