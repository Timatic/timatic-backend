<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DataTransferObjects\ApiClient;
use App\Services\ApiClientRegistry;
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
            'client_id' => ['required', 'string', Rule::in($this->clients()->ids())],
            'code' => ['required', 'string', 'max:255'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'redirect_uri' => ['required', 'string', Rule::in($this->clients()->redirectUrisFor($this->string('client_id')->toString()))],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function client(): ApiClient
    {
        return $this->clients()->findOrFail((string) $this->validated('client_id'));
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function codeVerifier(): string
    {
        return (string) $this->validated('code_verifier');
    }

    public function redirectUri(): string
    {
        return (string) $this->validated('redirect_uri');
    }

    public function deviceName(): string
    {
        return (string) $this->validated('device_name');
    }

    private function clients(): ApiClientRegistry
    {
        return app(ApiClientRegistry::class);
    }
}
