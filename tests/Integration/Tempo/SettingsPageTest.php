<?php

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\Tempo\DataTransferObjects\WorklogSyncRun;
use Timatic\Tempo\Enums\WorklogSyncStatus;
use Timatic\Tempo\Filament\Pages\SettingsPage;
use Timatic\Tempo\Jobs\SyncWorklogsJob;
use Timatic\Tempo\WorklogSyncTracker;

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

it('queues a worklog sync from the chosen day', function () {
    // Given
    Queue::fake();
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->callAction(TestAction::make('sync_worklogs')->schemaComponent('worklog_sync'), ['since' => '2026-06-01']);

    // Then
    Queue::assertPushed(SyncWorklogsJob::class, fn (SyncWorklogsJob $job) => $job->run->integrationId === $tempo->id
        && $job->run->updatedSinceMilliseconds === CarbonImmutable::parse('2026-05-31T22:00:00Z')->getTimestampMs());
});

it('shows since when and until when the worklogs are synced, with the result of the last sync', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => [
        'worklog_sync_from' => CarbonImmutable::parse('2026-05-31T22:00:00Z')->getTimestampMs(),
        'worklog_synced_until' => CarbonImmutable::parse('2026-11-20T13:30:00Z')->getTimestampMs(),
    ]]);
    app(WorklogSyncTracker::class)->close(new WorklogSyncRun(id: 'run-1', integrationId: $tempo->id, status: WorklogSyncStatus::Finished, updatedSinceMilliseconds: 0, deletedSinceMilliseconds: 0, startedAtMilliseconds: 0, latestUpdatedMilliseconds: 0, created: 12, updated: 3, deleted: 1, skipped: 2));

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Gesynchroniseerd vanaf 01-06-2026, bijgewerkt tot 20-11-2026 14:30.')
        ->assertSee('Laatste sync: 12 aangemaakt, 3 bijgewerkt, 1 verwijderd, 2 overgeslagen.');
});

it('shows how far a running worklog sync is', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => []]);
    app(WorklogSyncTracker::class)->claim(new WorklogSyncRun(
        id: 'run-1',
        integrationId: $tempo->id,
        status: WorklogSyncStatus::Running,
        updatedSinceMilliseconds: 0,
        deletedSinceMilliseconds: 0,
        startedAtMilliseconds: CarbonImmutable::parse('2026-11-01T10:00:00Z')->getTimestampMs(),
        latestUpdatedMilliseconds: 0,
        processed: 400,
        firstWorklogStartedAtMilliseconds: CarbonImmutable::parse('2026-06-01T10:00:00Z')->getTimestampMs(),
        currentWorklogStartedAtMilliseconds: CarbonImmutable::parse('2026-08-14T10:00:00Z')->getTimestampMs(),
    ));

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Bezig: 400 worklogs verwerkt, dagen 01-06-2026 t/m 14-08-2026 gesynchroniseerd.')
        ->assertSeeHtml('value="48" max="100"');
});

it('lists the Tempo authors whose worklogs are skipped because they have no Timatic user', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['unmatched_authors' => [
        ['account_id' => 'acc-9', 'name' => 'Bob Jones', 'email' => 'bob@example.com', 'worklogs' => 7],
    ]]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Bob Jones (bob@example.com): 7 worklogs overgeslagen');
});
