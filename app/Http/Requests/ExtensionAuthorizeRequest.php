<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ExtensionAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtensionAuthorizeRequest extends FormRequest
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
            'redirect_uri' => ['required', 'string', Rule::in(app(ExtensionAuthorizationService::class)->allowedRedirectUris())],
            'state' => ['required', 'string', 'max:255'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9\-_]{43}$/'],
            'code_challenge_method' => ['required', 'in:S256'],
        ];
    }

    public function redirectUri(): string
    {
        return (string) $this->validated('redirect_uri');
    }

    public function state(): string
    {
        return (string) $this->validated('state');
    }

    public function codeChallenge(): string
    {
        return (string) $this->validated('code_challenge');
    }
}
