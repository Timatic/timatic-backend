<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Webmozart\Assert\Assert;

class PermissionUpdateRequest extends FormRequest
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
        $permission = $this->route('permission');

        Assert::isInstanceOf($permission, Permission::class);

        return [
            'data.type' => ['required', 'string', 'in:permissions'],
            'data.attributes.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->ignore($permission->id),
            ],
        ];
    }
}
