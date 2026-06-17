<?php

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
    Mail::fake();
    Event::fake();
    Storage::fake();

    $month = Carbon::now()->month;
    $year = Carbon::now()->year;
    $this->loginUser(['permissions' => ['user']]);

    $entries = Entry::factory()->count(10)->create();

    /** @var Carbon $startOfMonth */
    $startOfMonth = Carbon::create($year, $month);

    /** @var Carbon $endOfMonth */
    $endOfMonth = $startOfMonth->clone()->endOfMonth();
    $entriesThisMonth = $entries->filter(function ($entry) use ($startOfMonth, $endOfMonth) {
        return $entry->started_at->between($startOfMonth, $endOfMonth);
    });

    $this->get(route('budgets.export-mail', [
        'exportType' => 'users-monthly-summary-excel',
        'year' => $year,
        'month' => $month,
    ]))->assertSuccessful();

    $reader = new Reader;
    $reader->open(Storage::disk('temp')->path("export_users-monthly-summary-excel_{$year}_{$month}.xlsx"));

    $rowCount = 0;
    $headerCount = 0;

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowCount++;
            if ($rowCount == 1) {
                $headerCount = count($row->toArray());
            }
        }
    }

    expect($rowCount)->toEqual($entriesThisMonth->unique('user_id')->count() + 1);
    expect($headerCount)->toEqual(11);
});
