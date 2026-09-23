<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DataTransferObjects\ApiClient;
use App\Services\ApiClientRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AuthorizeClientRequest extends FormRequest
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
            'redirect_uri' => ['required', 'string', Rule::in($this->clients()->redirectUrisFor($this->string('client_id')->toString()))],
            'state' => ['required', 'string', 'max:255'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9\-_]{43}$/'],
            'code_challenge_method' => ['required', 'in:S256'],
        ];
    }

    /**
     * A rejected request is a dead end: the caller registered the wrong parameters, so redirecting
     * back would send the browser round the login loop again. It gets a page of its own instead.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->view('oauth.error', status: 400)
        );
    }

    public function client(): ApiClient
    {
        return $this->clients()->findOrFail((string) $this->validated('client_id'));
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

    private function clients(): ApiClientRegistry
    {
        return app(ApiClientRegistry::class);
    }
}
