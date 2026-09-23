<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app['env'] = 'local';
});

it('rejects a consent form post without a csrf token', function () {
    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => ['access_token' => 'token'],
        'share_token' => 'share-token',
    ]);

    $response = $this->post(
        route('bitbucket.delegate.install-webhook', $integration->share_token),
        ['workspace_slug' => 'acme'],
    );

    $response->assertStatus(419);
});

it('accepts a provider webhook post without a csrf token', function () {
    $integration = Integration::factory()->create([
        'type' => 'bitbucket',
        'config' => ['webhook_secret' => 'secret'],
    ]);

    $response = $this->post(route('bitbucket.webhook', $integration->id), []);

    $response->assertStatus(403);
});

it('accepts an api post without a csrf token', function () {
    $response = $this->postJson(route('entries.store'), []);

    $response->assertStatus(401);
});
