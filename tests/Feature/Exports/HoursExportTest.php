<?php

use App\DataTransferObjects\ExportPeriod;
use App\Enums\HoursExportDimension;
use App\Exports\HoursExport;
use App\Jobs\ExportBudgetsJob;
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
    $user = $this->loginUser();
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
    Queue::assertPushed(ExportBudgetsJob::class, function (ExportBudgetsJob $job) use ($budget) {
        $period = (fn () => $this->period)->call($job);

        return $period == new ExportPeriod(
            year: null,
            month: null,
            fromMonth: '2026-01',
            toMonth: '2026-03',
            groupBy: HoursExportDimension::User,
            splitBy: HoursExportDimension::Budget,
            budgetId: $budget->id,
        );
    });
});

it('rejects an hours export without a month range or with the same group by and split by', function () {
    // Given
    $this->loginUser();

    // When
    $response = $this->getJson(route('budgets.export-mail', [
        'exportType' => 'hours-excel',
        'groupBy' => 'user',
        'splitBy' => 'user',
    ]));

    // Then
    $response->assertUnprocessable()->assertJsonValidationErrors(['fromMonth', 'toMonth', 'splitBy']);
});

it('exports the hours per user per budget per month', function () {
    // Given
    app()->setLocale('nl');
    $anna = User::factory()->create();
    $bob = User::factory()->create();
    $support = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Support']))->create();
    $development = Budget::factory()->has(BudgetVersion::factory()->state(['title' => 'Development']))->create();
    $entry = fn (User $user, Budget $budget, string $startedAt, int $minutes) => Entry::factory()->create([
        'user_id' => $user->id,
        'user_full_name' => $user === $anna ? 'Anna' : 'Bob',
        'budget_id' => $budget->id,
        'customer_id' => $budget->customer_id,
        'started_at' => $startedAt,
        'ended_at' => $startedAt,
        'minutes_spent' => $minutes,
    ]);
    $entry($anna, $support, '2026-01-15 10:00:00', 60);
    $entry($anna, $support, '2026-03-02 10:00:00', 90);
    $entry($anna, $development, '2026-02-10 10:00:00', 30);
    $entry($bob, $support, '2026-01-20 10:00:00', 120);
    $entry($bob, $development, '2026-04-01 10:00:00', 45);
    Storage::fake('temp');
    $path = Storage::disk('temp')->path('hours.xlsx');

    // When
    (new HoursExport(new ExportPeriod(
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
        ['Anna', 'Support', 1, 0, 1.5],
        ['Bob', 'Support', 2, 0, 0],
    ]);
});
