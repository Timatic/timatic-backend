<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('offers a connect button when jira is not connected yet', function () {
    $integration = Integration::factory()->create([
        'type' => 'jira',
        'config' => [],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('jira.delegate.show', $integration->share_token));

    $response->assertOk();
    $response->assertSee('Klik op de knop hieronder om Jira te verbinden met Timatic. U wordt doorgestuurd naar Jira om toestemming te verlenen.');
    $response->assertSee(route('jira.delegate.oauth-redirect', 'share-token'));
});

it('confirms the connection when jira has an access token and cloud id', function () {
    $integration = Integration::factory()->create([
        'type' => 'jira',
        'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1'],
        'share_token' => 'share-token',
    ]);

    $response = $this->get(route('jira.delegate.show', $integration->share_token));

    $response->assertOk();
    $response->assertSee('Jira succesvol verbonden.');
    $response->assertDontSee(route('jira.delegate.oauth-redirect', 'share-token'));
});
