<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('downloads an excel file', function () {
    Budget::query()->delete();
    Mail::fake();
    Event::fake();
    Storage::fake();

    $month = Carbon::now()->month;
    $year = Carbon::now()->year;
    $this->loginUser(['permissions' => ['user']]);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1))
        ->has(Entry::factory()->count(3))
        ->count(5)
        ->create();

    $this->get(route('budgets.export-mail', [
        'exportType' => 'budgets-monthly-excel',
        'year' => $year,
        'month' => $month,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path("export_budgets-monthly-excel_{$year}_{$month}.xlsx"));

    $rowCount = 0;
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowCount++;
        }
    }

    expect($rowCount)->toEqual(6);
});
