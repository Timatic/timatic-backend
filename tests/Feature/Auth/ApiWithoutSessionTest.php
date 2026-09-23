<?php

use App\Models\ApiToken;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

it('accepts a bearer token without any cookie', function () {
    Event::fake();

    $customer = Customer::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('entries.create');

    ApiToken::query()->create([
        'user_id' => $user->id,
        'title' => 'Chrome op Mac',
        'key' => hash('sha512', 'plain-text-token'),
    ]);

    $this->postJson(route('entries.store'), [
        'data' => [
            'type' => 'entries',
            'attributes' => [
                'isPaidPerHour' => false,
                'customerId' => $customer->id,
                'startedAt' => Carbon::now()->subDay(),
                'endedAt' => Carbon::now()->subDay()->addHour(),
                'entryType' => 'regular',
                'isInternal' => true,
                'hasOvertime' => false,
                'hasCustomerOvertime' => false,
            ],
        ],
    ], ['Authorization' => 'Bearer plain-text-token'])->assertCreated();
});

it('rejects a request that carries only a web session', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('entries.read');

    $this->actingAs($user, 'web');

    $this->getJson(route('entries.index'))->assertUnauthorized();
});

it('returns a validation failure as json without an accept header', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('entries.create');

    $this->actingAs($user, 'api');

    $this->post(route('entries.store'), [], ['Accept' => '*/*'])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/json');
});

it('never sets a cookie on an api response', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('entries.read');

    $this->actingAs($user, 'api');

    $response = $this->getJson(route('entries.index'))->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});
