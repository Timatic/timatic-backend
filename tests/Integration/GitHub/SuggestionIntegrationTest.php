<?php

use App\Models\Integration;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('lists github as active once an installation is adopted', function () {
    $this->loginUser();

    Integration::factory()->create([
        'type' => 'github',
        'config' => ['access_token' => 'ghu_user_token', 'installations' => [['id' => 4242, 'account' => 'acme']]],
    ]);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    $github = collect($response->json('data'))->firstWhere('id', 'github');

    expect($github['attributes']['label'])->toEqual('GitHub')
        ->and($github['attributes']['iconUrl'])->toEndWith('/icons/github.svg')
        ->and($github['attributes']['isActive'])->toBeTrue();
});
