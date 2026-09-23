<?php

namespace Timatic\GitHub;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly GitHubCredentials $credentials) {}

    /**
     * Reads repositories and issues on behalf of the app installation, so it keeps
     * working when no user is present.
     */
    public static function forInstallation(int $installationId): self
    {
        return new self(new GitHubCredentials(app(InstallationTokenService::class)->token($installationId)));
    }

    public static function forUser(string $userAccessToken): self
    {
        return new self(new GitHubCredentials($userAccessToken));
    }

    public static function forApp(): self
    {
        return new self(new GitHubCredentials(app(InstallationTokenService::class)->appJwt()));
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.github.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->credentials->token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
