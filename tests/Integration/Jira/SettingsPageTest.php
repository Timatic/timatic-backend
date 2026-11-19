<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\SettingsPage;

uses(LoginUser::class);

it('stores the mirrored issue settings from the settings page', function () {
    // Given
    config()->set('jira.client_id', 'client-id');
    config()->set('jira.client_secret', 'client-secret');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'cloud_url' => 'https://acme.atlassian.net']]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->fillForm(['mirrored_issues_enabled' => true, 'mirrored_issue_prefixes' => ['silo']])
        ->callAction('save');

    // Then
    expect($integration->refresh()->config['mirrored_issues_enabled'])->toBeTrue()
        ->and($integration->config['mirrored_issue_prefixes'])->toBe(['SILO'])
        ->and($integration->config['access_token'])->toBe('token');
});
