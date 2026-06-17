<?php

use App\Models\Budget;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach()->loginUser(['permissions' => ['user']]);

test('user can download budget entries export', function () {
    Event::fake();
    Storage::fake();

    /** @var Budget $budget */
    $budget = Budget::factory()->create();

    Entry::factory()->count(4)->create(['budget_id' => $budget->id]);

    ob_start();

    $this->get('/budgets/'.$budget->id.'/entries-export')->assertSuccessful();

    $fileContents = ob_get_clean();

    Storage::disk('temp')->put('tmp.xlsx', $fileContents);
    $reader = new Reader;

    $reader->open(Storage::disk('temp')->path('tmp.xlsx'));

    $sheet = $reader->getSheetIterator()->current();
    $reader->close();

    expect($sheet->getRowIterator())->toHaveCount(5);
});
