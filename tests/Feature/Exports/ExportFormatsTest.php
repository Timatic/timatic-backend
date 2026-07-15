<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the built-in export formats', function () {
    $this->loginUser();

    $response = $this->getJson(route('export-formats.index'));

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => 'budgets-monthly-excel',
        'type' => 'exportFormats',
        'attributes' => [
            'label' => 'Budget mutations',
            'periodOptions' => 'monthly',
            'extension' => 'xlsx',
        ],
    ]);
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([
        'budgets-monthly-excel',
        'budgets-excel',
        'entries-excel',
        'users-monthly-summary-excel',
    ]);
});

it('lists the exact globe export when the integration is configured', function () {
    $this->loginUser();
    Integration::create([
        'name' => 'Exact Globe',
        'type' => 'exact-globe',
        'config' => [
            'ledger_mapping' => [
                'project' => [
                    'usage_credit' => '28075',
                    'usage_debit' => '81135',
                    'release_credit' => '28075',
                    'release_debit' => '81131',
                ],
            ],
        ],
    ]);

    $response = $this->getJson(route('export-formats.index'));

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'id' => 'exact-globe-csv',
        'type' => 'exportFormats',
        'attributes' => [
            'label' => 'Budget mutations - Exact',
            'periodOptions' => 'monthly',
            'extension' => 'csv',
        ],
    ]);
});

it('hides the exact globe export when the integration has no ledger mapping', function () {
    $this->loginUser();
    Integration::create(['name' => 'Exact Globe', 'type' => 'exact-globe', 'config' => []]);

    $response = $this->getJson(route('export-formats.index'));

    $response->assertSuccessful();
    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain('exact-globe-csv');
});
