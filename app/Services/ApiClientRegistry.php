<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ApiClient;
use App\Exceptions\UnknownApiClientException;
use Illuminate\Support\Collection;

class ApiClientRegistry
{
    public function find(string $clientId): ?ApiClient
    {
        /** @var array<string, ApiClient> $clients */
        $clients = $this->all()->all();

        return $clients[$clientId] ?? null;
    }

    /**
     * @throws UnknownApiClientException
     */
    public function findOrFail(string $clientId): ApiClient
    {
        return $this->find($clientId) ?? throw UnknownApiClientException::forId($clientId);
    }

    /**
     * @return Collection<string, ApiClient>
     */
    public function all(): Collection
    {
        /** @var array<string, array{label: string, redirect_uris: list<string>, token_lifetime_days: int, auto_approve: bool}> $clients */
        $clients = config('api_clients.clients', []);

        return (new Collection($clients))->map(fn (array $client, string $id): ApiClient => new ApiClient(
            id: $id,
            label: $client['label'],
            redirectUris: $client['redirect_uris'],
            tokenLifetimeDays: $client['token_lifetime_days'],
            autoApprove: $client['auto_approve'],
        ));
    }

    /**
     * The redirect uris an unvalidated client id may send a code to. An unknown client allows
     * none, so the redirect uri rule fails alongside the client id rule instead of passing.
     *
     * @return list<string>
     */
    public function redirectUrisFor(string $clientId): array
    {
        $client = $this->find($clientId);

        return $client instanceof ApiClient ? $client->redirectUris : [];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values($this->all()->keys()->all());
    }
}
