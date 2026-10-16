<?php

use App\Models\ApiClient;
use App\Models\ApiToken;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('registers the plugin as a client that logs in on a loopback port and stays logged in while used', function () {
    $client = ApiClient::findOrFail('claude-code');

    expect($client->allowsRedirectUri('http://127.0.0.1:53124/callback'))->toBeTrue()
        ->and($client->sliding_expiration)->toBeTrue()
        ->and($client->auto_approve)->toBeFalse();
});

it('offers every user to install the Claude Code plugin', function () {
    $this->loginUser();

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    $claudeCode = collect($response->json('data'))->firstWhere('id', 'claude_code');

    expect($claudeCode['attributes']['label'])->toEqual('Claude Code')
        ->and($claudeCode['attributes']['iconUrl'])->toEndWith('/icons/claude_code.svg')
        ->and($claudeCode['attributes']['isCompanyManaged'])->toBeFalse()
        ->and($claudeCode['attributes']['isActive'])->toBeFalse()
        ->and($claudeCode['attributes']['connectUrl'])->toEqual('https://github.com/Timatic/timatic-claude')
        ->and($claudeCode['attributes']['connectLabel'])->toEqual('Install');
});

it('shows Claude Code as active once the user logged in with the plugin', function () {
    $user = $this->loginUser();

    ApiToken::factory()->create(['user_id' => $user->id, 'client_id' => 'claude-code']);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    expect(collect($response->json('data'))->firstWhere('id', 'claude_code')['attributes']['isActive'])->toBeTrue();
});
