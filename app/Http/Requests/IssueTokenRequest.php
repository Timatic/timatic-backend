<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ApiClient;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTokenRequest extends FormRequest
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
            'client_id' => ['required', 'string', Rule::in(ApiClient::ids())],
            'code' => ['required', 'string', 'max:255'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'redirect_uri' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $client = ApiClient::find($this->string('client_id')->toString());

                    if (! is_string($value) || ! $client?->allowsRedirectUri($value)) {
                        $fail('The selected redirect uri is invalid.');
                    }
                },
            ],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
