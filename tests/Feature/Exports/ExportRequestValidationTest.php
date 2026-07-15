<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('rejects an unknown export type', function () {
    $this->loginUser();

    $this->getJson(route('budgets.export-mail', [
        'exportType' => 'unknown-export',
        'year' => 2024,
        'month' => 10,
    ]))->assertUnprocessable()->assertJsonValidationErrors('exportType');
});

it('rejects a monthly export without a month', function () {
    $this->loginUser();

    $this->getJson(route('budgets.export-mail', [
        'exportType' => 'budgets-monthly-excel',
        'year' => 2024,
    ]))->assertUnprocessable()->assertJsonValidationErrors('month');
});

it('accepts an export without period options without a year and month', function () {
    $this->loginUser();
    Mail::fake();
    Storage::fake();
    Storage::fake('temp');

    $this->getJson(route('budgets.export-mail', [
        'exportType' => 'budgets-excel',
    ]))->assertStatus(202);
});

it('accepts a monthly and yearly export without a month', function () {
    $this->loginUser();
    Mail::fake();
    Storage::fake('s3');
    Storage::fake('temp');

    $this->getJson(route('budgets.export-mail', [
        'exportType' => 'entries-excel',
        'year' => 2024,
    ]))->assertStatus(202);
});
