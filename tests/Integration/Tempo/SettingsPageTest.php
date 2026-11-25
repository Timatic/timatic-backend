<?php

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\Tempo\Filament\Pages\SettingsPage;
use Timatic\Tempo\Jobs\BackfillWorklogsJob;

uses(LoginUser::class);

it('stores the token, the linked Jira instance, both sync switches and the mirrored issue settings', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => []]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->assertSuccessful()
        ->fillForm(['api_token' => 'secret-token', 'jira_integration_id' => $jira->id, 'sync_to_timatic' => true, 'sync_to_tempo' => true, 'mirrored_issues_enabled' => true, 'mirrored_issue_prefixes' => ['abc']])
        ->callAction('save');

    // Then
    expect($tempo->refresh()->config)->toBe([
        'api_token' => 'secret-token',
        'jira_integration_id' => $jira->id,
        'sync_to_timatic' => true,
        'sync_to_tempo' => true,
        'mirrored_issues_enabled' => true,
        'mirrored_issue_prefixes' => ['ABC'],
    ]);
});

it('keeps the stored token when the field is left empty', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->fillForm(['sync_to_tempo' => true])
        ->callAction('save');

    // Then
    expect($tempo->refresh()->config['api_token'])->toBe('secret-token')
        ->and($tempo->config['sync_to_tempo'])->toBeTrue();
});

it('queues one import job per month of the chosen period and shows how many months are still queued', function () {
    // Given
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-20T12:00:00Z'));
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->callAction(TestAction::make('import_worklogs')->schemaComponent('worklog_sync'), ['from' => '2026-06-15', 'until' => '2026-08-10'])
        ->assertNotified('Import van 3 maanden ingepland.')
        ->assertSee('Worklogs importeren: nog 3 van 3 maanden in de wachtrij.');

    // Then
    expect(Queue::pushed(BackfillWorklogsJob::class)->map(fn (BackfillWorklogsJob $job) => [$job->from->toDateString(), $job->to->toDateString()])->all())->toBe([
        ['2026-06-15', '2026-06-30'],
        ['2026-07-01', '2026-07-31'],
        ['2026-08-01', '2026-08-10'],
    ]);
});

it('shows when the worklogs were last synced', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => [
        'worklog_synced_until' => CarbonImmutable::parse('2026-11-20T13:30:00Z')->getTimestampMs(),
    ]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Laatste sync met Tempo: 20-11-2026 14:30.');
});
