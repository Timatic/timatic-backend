<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    use ValidatedAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<array<string>>
     */
    public function rules(): array
    {
        $userId = $this->route('user') instanceof User
            ? $this->route('user')->id
            : null;

        return [
            'data.type' => ['string', 'in:users'],
            'data.attributes.externalId' => ['string'],
            'data.attributes.email' => ['string', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'data.attributes.givenName' => ['nullable', 'string', 'max:255'],
            'data.attributes.familyName' => ['nullable', 'string', 'max:255'],
            'data.relationships.team.data.type' => ['nullable', 'string', 'in:teams'],
            'data.relationships.team.data.id' => ['nullable', 'string', 'exists:teams,id'],
        ];
    }
}
