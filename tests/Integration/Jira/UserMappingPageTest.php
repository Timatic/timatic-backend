<?php

use App\Models\Integration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\UserMappingPage;
use Timatic\Jira\Models\UserMapping;
use Timatic\Jira\Requests\GetUsersRequest;

uses(LoginUser::class);
uses(RefreshDatabase::class);

it('loads every person of the Jira instance and links them to the Timatic user with the same e-mail address', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $ann = User::factory()->create(['email' => 'ann@example.com']);
    MockClient::global([
        GetUsersRequest::class => fn (PendingRequest $request) => MockResponse::make($request->query()->get('startAt') === 0
            ? [
                ...array_fill(0, GetUsersRequest::LIMIT - 1, ['accountId' => 'app-1', 'accountType' => 'app', 'displayName' => 'Automation']),
                ['accountId' => 'acc-1', 'accountType' => 'atlassian', 'displayName' => 'Ann Smith', 'emailAddress' => 'Ann@example.com'],
            ]
            : [['accountId' => 'acc-2', 'accountType' => 'atlassian', 'displayName' => 'Bob Jones']]),
    ]);

    // When
    Livewire::test(UserMappingPage::class, ['record' => $jira->id])->callAction('refresh');

    // Then
    expect(UserMapping::query()->orderBy('account_id')->get(['account_id', 'display_name', 'email', 'user_id'])->toArray())->toBe([
        ['account_id' => 'acc-1', 'display_name' => 'Ann Smith', 'email' => 'Ann@example.com', 'user_id' => $ann->id],
        ['account_id' => 'acc-2', 'display_name' => 'Bob Jones', 'email' => null, 'user_id' => null],
    ]);
});
