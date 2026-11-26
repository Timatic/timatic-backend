<?php

use App\Models\Budget;
use App\Models\Integration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\EpicMappingPage;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Filament\Pages\UserMappingPage;
use Timatic\Jira\Models\EpicMapping;
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
    $ann = UserMapping::query()->create(['integration_id' => $jira->id, 'account_id' => 'acc-1', 'display_name' => 'Ann Smith']);
    $linkedSince = UserMapping::query()->create(['integration_id' => $jira->id, 'account_id' => 'acc-2', 'display_name' => 'Bob Jones', 'user_id' => User::factory()->create()->id]);
    $acme = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $beta = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'BETA', 'project_name' => 'Beta']);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => [
        'api_token' => 'secret-token',
        'jira_integration_id' => $jira->id,
        'worklog_skipped_user_mapping_ids' => [$ann->id, $linkedSince->id],
        'worklog_skipped_project_mapping_ids' => [$acme->id, $beta->id],
    ]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('1 gebruiker koppelen')
        ->assertSee('2 projecten koppelen in Jira')
        ->assertSeeHtml(e(UserMappingPage::getUrl(['record' => $jira->id, 'filters' => ['id' => ['ids' => "{$ann->id}"]]])))
        ->assertSeeHtml(e(ProjectMappingPage::getUrl(['record' => $jira->id, 'filters' => ['id' => ['ids' => "{$acme->id},{$beta->id}"]]])));
});

it('links to the Jira epics on which the sync skipped a worklog', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1']]);
    $budget = Budget::factory()->create();
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support', 'is_mapped_per_epic' => true]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $unmapped = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-3', 'epic_name' => 'Acme']);
    $mappedSince = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-4', 'epic_name' => 'Beta', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    $ofRemovedProject = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'ACME-1', 'epic_name' => 'Webshop']);
    $tempo = Integration::create(['title' => 'Tempo', 'type' => 'tempo', 'config' => [
        'api_token' => 'secret-token',
        'jira_integration_id' => $jira->id,
        'worklog_skipped_epic_mapping_ids' => [$unmapped->id, $mappedSince->id, $ofRemovedProject->id],
    ]]);

    // When
    $page = Livewire::test(SettingsPage::class, ['record' => $tempo->id]);

    // Then
    $page->assertSee('1 epic koppelen')
        ->assertSeeHtml(e(EpicMappingPage::getUrl(['record' => $jira->id, 'filters' => ['id' => ['ids' => "{$unmapped->id}"]]])));
});
