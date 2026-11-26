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

it('enables the worklog import as soon as the token and the Jira instance are saved', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => []]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->fillForm(['api_token' => 'secret-token', 'jira_integration_id' => $jira->id])
        ->callAction('save');

    // Then
    $page->assertActionEnabled(TestAction::make('import_worklogs')->schemaComponent('worklog_sync'));
});

it('lists a Jira instance without a title by its default name', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => null, 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => []]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSuccessful()->assertSee($jira->display_title);
});

it('warns when the linked Jira instance is no longer connected', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('Jira is niet meer verbonden');
});

it('proposes today in the preferred timezone as the end of the import period', function () {
    // Given
    $this->travelTo(CarbonImmutable::parse('2026-06-20T12:00:00Z'));
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->mountAction(TestAction::make('import_worklogs')->schemaComponent('worklog_sync'));

    // Then
    expect($page->get('mountedActions.0.data.until'))->toBe('2026-06-20');
});

it('queues one import job per week of the chosen period and shows how many weeks are still queued', function () {
    // Given
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-20T12:00:00Z'));
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $tempo->id])
        ->callAction(TestAction::make('import_worklogs')->schemaComponent('worklog_sync'), ['from' => '2026-06-15', 'until' => '2026-07-01'])
        ->assertNotified('Import van 3 weken ingepland.')
        ->assertSee('Worklogs importeren: nog 3 van 3 weken in de wachtrij.');

    // Then
    expect(Queue::pushed(BackfillWorklogsJob::class)->map(fn (BackfillWorklogsJob $job) => [$job->from->toDateString(), $job->to->toDateString()])->all())->toBe([
        ['2026-06-15', '2026-06-21'],
        ['2026-06-22', '2026-06-28'],
        ['2026-06-29', '2026-07-01'],
    ]);
});
