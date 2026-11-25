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
use Timatic\Tempo\Jobs\SyncWorklogsJob;

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

it('queues the sync and one import job per month from the chosen day and shows how many months are still queued', function () {
    // Given
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-08-10T12:00:00Z'));
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->callAction(TestAction::make('sync_worklogs')->schemaComponent('worklog_sync'), ['since' => '2026-06-15'])
        ->assertNotified('Sync en import van 3 maanden ingepland.')
        ->assertSee('Oudere worklogs importeren: nog 3 van 3 maanden in de wachtrij.');

    // Then
    Queue::assertPushed(SyncWorklogsJob::class, fn (SyncWorklogsJob $job) => $job->tempoId === $tempo->id);
    expect(Queue::pushed(BackfillWorklogsJob::class)->map(fn (BackfillWorklogsJob $job) => [$job->from->toDateString(), $job->to->toDateString()])->all())->toBe([
        ['2026-06-15', '2026-06-30'],
        ['2026-07-01', '2026-07-31'],
        ['2026-08-01', '2026-08-10'],
    ]);
});

it('shows since when and until when the worklogs are synced', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => [
        'worklog_sync_from' => CarbonImmutable::parse('2026-05-31T22:00:00Z')->getTimestampMs(),
        'worklog_synced_until' => CarbonImmutable::parse('2026-11-20T13:30:00Z')->getTimestampMs(),
    ]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Gesynchroniseerd vanaf 01-06-2026, bijgewerkt tot 20-11-2026 14:30.');
});
