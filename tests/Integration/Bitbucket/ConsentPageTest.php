<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Bitbucket\Requests\GetWorkspacesRequest;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('offers a connect button when bitbucket is not connected yet', function () {
    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => [],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('bitbucket.delegate.show', $integration->share_token));

    $response->assertOk();
    $response->assertSee(route('bitbucket.delegate.oauth-redirect', 'share-token'));
});

it('lists the workspaces to install the webhook on once bitbucket is connected', function () {
    MockClient::global([
        GetWorkspacesRequest::class => MockResponse::make([
            'values' => [
                ['workspace' => ['slug' => 'acme'], 'administrator' => true],
                ['workspace' => ['slug' => 'read-only'], 'administrator' => false],
            ],
        ]),
    ]);

    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => ['access_token' => 'token', 'expires_at' => now()->addHour()->timestamp],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('bitbucket.delegate.show', $integration->share_token).'?connected=1');

    $response->assertOk();
    $response->assertSee('Bitbucket succesvol verbonden. Selecteer nu een workspace om de webhook te installeren.');
    $response->assertSee('acme');
    $response->assertSee('read-only (geen beheerder)');
    $response->assertSee('Workspaces met "(geen beheerder)" zijn uitgeschakeld omdat u daar geen beheerdersrechten heeft. Alleen beheerders kunnen de webhook installeren.');
});

it('reports a failed webhook installation', function () {
    MockClient::global([
        GetWorkspacesRequest::class => MockResponse::make(['values' => []]),
    ]);

    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => ['access_token' => 'token', 'expires_at' => now()->addHour()->timestamp],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('bitbucket.delegate.show', $integration->share_token).'?error=webhook_failed');

    $response->assertOk();
    $response->assertSee('Webhook installatie mislukt. Probeer het opnieuw.');
});

it('confirms the installation once the webhook exists', function () {
    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => ['access_token' => 'token', 'webhook_uuid' => '{uuid}'],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('bitbucket.delegate.show', $integration->share_token).'?webhook_installed=1');

    $response->assertOk();
    $response->assertSee('Webhook succesvol geïnstalleerd. De integratie is volledig geconfigureerd.');
});
