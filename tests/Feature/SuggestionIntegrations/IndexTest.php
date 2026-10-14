<?php

use App\Models\ApiToken;
use App\Models\Integration;
use Tests\Concerns\LoginUser;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;

uses(LoginUser::class);

it('lists a company managed integration once an admin enabled it', function () {
    $this->loginUser();

    Integration::factory()->create(['type' => 'jira']);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    $jira = collect($response->json('data'))->firstWhere('id', 'jira');

    expect($jira['attributes']['label'])->toEqual('Jira');
    expect($jira['attributes']['iconUrl'])->toEndWith('/icons/jira.svg');
    expect($jira['attributes']['isCompanyManaged'])->toBeTrue();
    expect($jira['attributes']['isActive'])->toBeTrue();
});

it('hides a company managed integration that is not enabled', function () {
    $this->loginUser();

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain('bitbucket');
});

it('offers a signed link to connect google calendar', function () {
    $this->loginUser();

    Integration::factory()->create(['type' => 'google_calendar']);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    $calendar = collect($response->json('data'))->firstWhere('id', 'google_calendar');

    expect($calendar['attributes']['isCompanyManaged'])->toBeFalse();
    expect($calendar['attributes']['isActive'])->toBeFalse();
    expect($calendar['attributes']['connectUrl'])->toContain('signature=');
});

it('shows google calendar as active once the user connected it', function () {
    $user = $this->loginUser();

    Integration::factory()->create(['type' => 'google_calendar']);
    GoogleCalendarConnection::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    expect(collect($response->json('data'))->firstWhere('id', 'google_calendar')['attributes']['isActive'])->toBeTrue();
});

it('shows the chrome extension as active once the user connected it', function () {
    $user = $this->loginUser();

    ApiToken::factory()->create(['user_id' => $user->id, 'client_id' => 'chrome-extension']);

    $response = $this->getJson(route('suggestion-integrations.index'))->assertOk();

    expect(collect($response->json('data'))->firstWhere('id', 'chrome_extension')['attributes']['isActive'])->toBeTrue();
});
