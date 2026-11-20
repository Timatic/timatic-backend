<?php

use App\DataTransferObjects\ExportParameters;
use App\Enums\HoursExportDimension;
use App\Exports\HoursExport;
use App\Jobs\ExportBudgetsJob;
use App\Mail\ExportEmail;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('queues an hours export with group by, split by and filters', function () {
    // Given
    $this->loginUser(permissions: ['entries.read']);
    $budget = Budget::factory()->create();
    Queue::fake();

    // When
    $response = $this->getJson(route('budgets.export-mail', [
        'exportType' => 'hours-excel',
        'fromMonth' => '2026-01',
        'toMonth' => '2026-03',
        'groupBy' => 'user',
        'splitBy' => 'budget',
        'budgetId' => $budget->id,
    ]));

    // Then
    $response->assertAccepted();
    Queue::assertPushed(ExportBudgetsJob::class, fn (ExportBudgetsJob $job) => $job->period == new ExportParameters(
        year: null,
        month: null,
        fromMonth: '2026-01',
        toMonth: '2026-03',
        groupBy: HoursExportDimension::User,
        splitBy: HoursExportDimension::Budget,
        budgetId: $budget->id,
    ));
});

it('forbids an hours export to a user who may not read entries', function () {
    // Given
    $this->loginUser(permissions: ['user']);

    // When
    $response = $this->getJson(route('budgets.export-mail', [
        'exportType' => 'hours-excel',
        'fromMonth' => '2026-01',
        'toMonth' => '2026-03',
        'groupBy' => 'user',
    ]));

    // Then
    $response->assertForbidden();
});

it('rejects an hours export without a month range or with the same group by and split by', function () {
    // Given
    $this->loginUser(permissions: ['entries.read']);

    // When
    $response = $this->getJson(route('budgets.export-mail', [
        'exportType' => 'hours-excel',
        'groupBy' => 'user',
        'splitBy' => 'user',
    ]));

    // Then
    $response->assertUnprocessable()->assertJsonValidationErrors(['fromMonth', 'toMonth', 'splitBy']);
});

it('rejects an hours export spanning more than 24 months', function () {
    // Given
    $this->loginUser(permissions: ['entries.read']);

    // When
    $response = $this->getJson(route('budgets.export-mail', [
        'exportType' => 'hours-excel',
        'fromMonth' => '2026-01',
        'toMonth' => '2028-01',
        'groupBy' => 'user',
    ]));

    // Then
    $response->assertUnprocessable()->assertJsonValidationErrors(['toMonth']);
});

it('mentions the month range in the export email', function () {
    // When
    $mail = new ExportEmail('export.xlsx', new ExportParameters(year: null, month: null, fromMonth: '2026-01', toMonth: '2026-03'), 'hours-excel');

    // Then
    expect($mail->formattedDate)->toBe('January 2026 - March 2026');
});

it('exports the hours per user per budget per month', function () {
    // Given
    app()->setLocale('nl');
    $anna = User::factory()->create();
    $bob = User::factory()->create();
    $support = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Support']))->create();
    $development = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Development']))->create();
    Entry::factory()->create(['user_id' => $anna->id, 'user_full_name' => 'Anna', 'budget_id' => $support->id, 'started_at' => '2026-01-15 10:00:00', 'ended_at' => '2026-01-15 10:00:00', 'minutes_spent' => 60]);
    // 00:30 on 1 February in the preferred timezone.
    Entry::factory()->create(['user_id' => $anna->id, 'user_full_name' => 'Anna', 'budget_id' => $support->id, 'started_at' => '2026-01-31 23:30:00', 'ended_at' => '2026-01-31 23:30:00', 'minutes_spent' => 30]);
    Entry::factory()->create(['user_id' => $anna->id, 'user_full_name' => 'Anna', 'budget_id' => $support->id, 'started_at' => '2026-03-02 10:00:00', 'ended_at' => '2026-03-02 10:00:00', 'minutes_spent' => 90]);
    Entry::factory()->create(['user_id' => $anna->id, 'user_full_name' => 'Anna', 'budget_id' => $development->id, 'started_at' => '2026-02-10 10:00:00', 'ended_at' => '2026-02-10 10:00:00', 'minutes_spent' => 30]);
    Entry::factory()->create(['user_id' => $anna->id, 'user_full_name' => 'Anna', 'budget_id' => null, 'is_internal' => true, 'started_at' => '2026-03-10 10:00:00', 'ended_at' => '2026-03-10 10:00:00', 'minutes_spent' => 15]);
    Entry::factory()->create(['user_id' => $bob->id, 'user_full_name' => 'Bob', 'budget_id' => $support->id, 'started_at' => '2026-01-20 10:00:00', 'ended_at' => '2026-01-20 10:00:00', 'minutes_spent' => 120]);
    Entry::factory()->create(['user_id' => $bob->id, 'user_full_name' => 'Bob', 'budget_id' => null, 'is_internal' => false, 'started_at' => '2026-01-21 10:00:00', 'ended_at' => '2026-01-21 10:00:00', 'minutes_spent' => 45]);
    Entry::factory()->create(['user_id' => $bob->id, 'user_full_name' => 'Bob', 'budget_id' => $development->id, 'started_at' => '2026-02-03 10:00:00', 'ended_at' => '2026-02-03 11:00:00', 'minutes_spent' => 60]);
    Entry::factory()->correction()->create(['user_id' => $bob->id, 'user_full_name' => 'Bob', 'budget_id' => $development->id, 'started_at' => '2026-02-04 11:00:00', 'ended_at' => '2026-02-04 10:00:00', 'minutes_spent' => -60]);
    Entry::factory()->create(['user_id' => $bob->id, 'user_full_name' => 'Bob', 'budget_id' => $development->id, 'started_at' => '2026-04-01 10:00:00', 'ended_at' => '2026-04-01 10:00:00', 'minutes_spent' => 45]);
    Storage::fake('temp');
    $path = Storage::disk('temp')->path('hours.xlsx');

    // When
    (new HoursExport(new ExportParameters(
        year: null,
        month: null,
        fromMonth: '2026-01',
        toMonth: '2026-03',
        groupBy: HoursExportDimension::User,
        splitBy: HoursExportDimension::Budget,
    )))->export($path);

    // Then
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    expect($rows)->toBe([
        ['Gebruiker', 'Budget', '2026-01', '2026-02', '2026-03'],
        ['Anna', 'Development', 0, 0.5, 0],
        ['Anna', 'Intern', 0, 0, 0.25],
        ['Anna', 'Support', 1, 0.5, 1.5],
        ['Bob', 'Per uur', 0.75, 0, 0],
        ['Bob', 'Support', 2, 0, 0],
    ]);
});

it('exports the hours per ticket type without a split, filtered on a budget', function () {
    // Given
    app()->setLocale('nl');
    $support = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Support']))->create();
    $development = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Development']))->create();
    Entry::factory()->create(['ticket_type' => 'Bug', 'budget_id' => $support->id, 'started_at' => '2026-01-15 10:00:00', 'ended_at' => '2026-01-15 10:00:00', 'minutes_spent' => 60]);
    Entry::factory()->create(['ticket_type' => 'Bug', 'budget_id' => $support->id, 'started_at' => '2026-03-02 10:00:00', 'ended_at' => '2026-03-02 10:00:00', 'minutes_spent' => 90]);
    Entry::factory()->create(['ticket_type' => null, 'budget_id' => $support->id, 'started_at' => '2026-01-20 10:00:00', 'ended_at' => '2026-01-20 10:00:00', 'minutes_spent' => 120]);
    Entry::factory()->create(['ticket_type' => 'Bug', 'budget_id' => $development->id, 'started_at' => '2026-02-10 10:00:00', 'ended_at' => '2026-02-10 10:00:00', 'minutes_spent' => 30]);
    Storage::fake('temp');
    $path = Storage::disk('temp')->path('hours.xlsx');

    // When
    (new HoursExport(new ExportParameters(
        year: null,
        month: null,
        fromMonth: '2026-01',
        toMonth: '2026-03',
        groupBy: HoursExportDimension::TicketType,
        budgetId: $support->id,
    )))->export($path);

    // Then
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    expect($rows)->toBe([
        ['Tickettype', '2026-01', '2026-02', '2026-03'],
        ['Bug', 1, 0, 1.5],
        ['Geen tickettype', 2, 0, 0],
    ]);
});
