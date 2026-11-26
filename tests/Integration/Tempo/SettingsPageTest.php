<?php

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Filament\Pages\UserMappingPage;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Models\UserMapping;
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

it('links to the Jira users and projects on which the sync skipped a worklog', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => ['api_token' => 'secret-token', 'jira_integration_id' => $jira->id]]);
    UserMapping::query()->create(['integration_id' => $jira->id, 'account_id' => 'acc-1', 'display_name' => 'Ann Smith'])->forceFill(['worklog_skipped_at' => now()])->save();
    UserMapping::query()->create(['integration_id' => $jira->id, 'account_id' => 'acc-2', 'display_name' => 'Bob Jones']);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme'])->forceFill(['worklog_skipped_at' => now()])->save();
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'BETA', 'project_name' => 'Beta'])->forceFill(['worklog_skipped_at' => now()])->save();
    $skippedWorklogsFilter = ['filters' => ['skipped_worklogs' => ['isActive' => true]]];

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('1 gebruiker koppelen')
        ->assertSee('2 projecten koppelen in Jira')
        ->assertSeeHtml(e(UserMappingPage::getUrl(['record' => $jira->id, ...$skippedWorklogsFilter])))
        ->assertSeeHtml(e(ProjectMappingPage::getUrl(['record' => $jira->id, ...$skippedWorklogsFilter])));
});
