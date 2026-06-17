<?php

use App\Exports\EntriesExport;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
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

it('creates an excel file', function () {
    Mail::fake();
    Storage::fake();
    Event::fake();

    $month = Carbon::now()->month;
    $year = Carbon::now()->year;
    $this->loginUser(['permissions' => ['user']]);

    $customer = Customer::factory()->create();

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1))
        ->has(
            Entry::factory()->state([
                'minutes_spent' => 10,
                'started_at' => Carbon::now(),
                'customer_id' => $customer->id,
            ])->count(2)
        )
        ->count(5)
        ->create([
            'customer_id' => $customer->id,
        ]);

    $this->get(route('budgets.export-mail', [
        'exportType' => 'entries-excel',
        'year' => $year,
        'month' => $month,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path("export_entries-excel_{$year}_{$month}.xlsx"));

    $rowCount = 0;
    $entries = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowCount++;
            $entries[] = $row->toArray();

        }
    }
    expect($rowCount)->toEqual(11);
    expect($entries[1][4])->toEqual($customer->external_id);
});

it('can export entries with deleted customer', function () {
    Mail::fake();
    Event::fake();
    Storage::fake('s3');
    $month = Carbon::now()->month;
    $year = Carbon::now()->year;

    $customer = Customer::factory()->create();

    /** @var Budget $budget */
    Budget::factory()
        ->has(BudgetVersion::factory()->count(1))
        ->has(
            Entry::factory()->state([
                'started_at' => Carbon::now(),
                'customer_id' => $customer->id,
            ])->count(2)
        )
        ->create([
            'customer_id' => $customer->id,
        ]);

    $customer->delete();

    $export = new EntriesExport(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
    expect($export->collection())->toHaveCount(2);
});
