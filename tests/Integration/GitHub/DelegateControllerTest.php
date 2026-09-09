<?php

use App\Models\Integration;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('offers the github connect button to a delegate without a token', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $integration->generateShareToken();

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee(route('github.delegate.oauth-redirect', $integration->share_token));
});

it('adopts every installation the delegate can reach', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 2,
            'installations' => [
                ['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']],
                ['id' => 5353, 'account' => ['login' => 'acme-labs', 'type' => 'Organization']],
            ],
        ]),
    ]);

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('acme, acme-labs');

    expect($integration->refresh()->config['installations'])->toBe([
        ['id' => 4242, 'account' => 'acme'],
        ['id' => 5353, 'account' => 'acme-labs'],
    ]);
});

it('points the delegate at the app installation page when nothing is installed yet', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make(['total_count' => 0, 'installations' => []]),
    ]);

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('https://github.com/apps/timatic/installations/new');
});

it('keeps the stored installations when GitHub cannot be reached', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
        'installations' => [['id' => 4242, 'account' => 'acme']],
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make(['message' => 'Bad credentials'], 401),
    ]);

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('acme');

    expect($integration->refresh()->config['installations'])->toBe([['id' => 4242, 'account' => 'acme']]);
});
