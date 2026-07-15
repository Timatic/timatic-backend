<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('has a change id in the export if given', function () {
    Mail::fake();
    Event::fake();
    Storage::fake();

    $this->loginUser(permissions: ['user']);

    $this->travelTo('2023-11-07');

    Budget::factory()
        ->has(BudgetVersion::factory([
            'change_id' => '#changeId123',
        ])->count(1))
        ->has(Entry::factory()->count(3))
        ->count(5)
        ->create();

    $this->get(route('budgets.export-mail', [
        'exportType' => 'budgets-excel',
        'month' => 7,
        'year' => 2023,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path('export_budgets-excel_2023_7.xlsx'));

    $sheet = $reader->getSheetIterator()->current();
    $rows = $sheet->getRowIterator();

    $reader->close();

    expect($rows)->toHaveCount(6);

    $changeId = false;

    foreach ($rows as $row) {
        if (in_array('#changeId123', $row->toArray())) {
            $changeId = true;
            break;
        }

    }

    expect($changeId)->toBeTrue('Expected changeId #changeId123 not found in export');

});

it('does not break if no change id is given', function () {
    Mail::fake();
    Event::fake();
    Storage::fake();

    $this->loginUser(permissions: ['user']);

    $this->travelTo('2023-11-07');

    Budget::factory()
        ->has(BudgetVersion::factory([
            'change_id' => null,
        ])->count(1))
        ->has(Entry::factory()->count(3))
        ->count(1)
        ->create();

    $this->get(route('budgets.export-mail', [
        'exportType' => 'budgets-excel',
        'year' => 2023,
        'month' => 7,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path('export_budgets-excel_2023_7.xlsx'));

    $rowCount = 0;
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowCount++;
        }
    }
    $reader->close();

    expect($rowCount)->toEqual(2);
});

it('exports and allows downloading of budget data', function () {
    Mail::fake();
    Event::fake();
    Storage::fake();

    $this->loginUser(permissions: ['user']);

    $this->travelTo('2023-11-07');

    Budget::factory()
        ->has(BudgetVersion::factory(['change_id' => '#changeId123'])->count(1))
        ->has(Entry::factory()->count(3))
        ->count(1)
        ->create();

    $this->get(route('budgets.export-mail', [
        'exportType' => 'budgets-excel',
        'year' => 2023,
        'month' => 7,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path('export_budgets-excel_2023_7.xlsx'));

    $rowCount = 0;
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowCount++;
        }
    }
    $reader->close();

    expect($rowCount)->toEqual(2);

    $fileName = 'export_budgets-excel_2023_7.xlsx';
    Storage::assertExists($fileName);

    $downloadResponse = $this->get(route('download.export', ['fileName' => $fileName]));

    $downloadResponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename='.$fileName);

});
