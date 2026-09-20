<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ExtensionAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtensionTokenRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'redirect_uri' => ['required', 'string', Rule::in(app(ExtensionAuthorizationService::class)->allowedRedirectUris())],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
