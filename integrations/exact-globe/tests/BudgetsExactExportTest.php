<?php

use App\DataTransferObjects\BudgetMutation;
use App\Models\Budget;
use App\Models\Customer;
use App\Services\BudgetUsageService;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Timatic\ExactGlobe\BudgetsExactExport;
use Timatic\ExactGlobe\DataTransferObjects\LedgerMapping;
use Timatic\ExactGlobe\ExactGlobeExportProvider;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('writes a credit and debit row per verbruik and vrijval mutation with the mapped ledger ids', function () {
    $budget = Budget::factory()
        ->for(Customer::factory()->state(['external_id' => 'CUST-42']))
        ->create(['budget_type_id' => 'project']);

    $mutation = new BudgetMutation($budget);
    $mutation->usedCredit = BigDecimal::of('10.5');
    $mutation->expiredCredit = BigDecimal::of('2');

    $usageService = mock(BudgetUsageService::class);
    $usageService->shouldReceive('get')->andReturn(new EloquentCollection([$mutation]));

    $ledgerMappings = new Collection([
        'project' => new LedgerMapping('project', '1001', '1002', '2001', '2002'),
    ]);

    $filePath = tempnam(sys_get_temp_dir(), 'exact').'.csv';
    (new BudgetsExactExport(2026, 6, $usageService, $ledgerMappings))->export($filePath);

    $rows = array_map('str_getcsv', file($filePath));

    expect($rows)->toHaveCount(5);
    [$verbruikCredit, $verbruikDebit, $vrijvalCredit, $vrijvalDebit] = array_slice($rows, 1);
    expect($verbruikCredit[8])->toBe('1001')
        ->and($verbruikCredit[12])->toBe('+10,5')
        ->and($verbruikCredit[9])->toBe('CUST-42')
        ->and($verbruikDebit[8])->toBe('1002')
        ->and($verbruikDebit[12])->toBe('-10,5')
        ->and($vrijvalCredit[8])->toBe('2001')
        ->and($vrijvalCredit[12])->toBe('+2')
        ->and($vrijvalDebit[8])->toBe('2002')
        ->and($vrijvalDebit[12])->toBe('-2');
});

it('skips budgets whose type has no ledger mapping', function () {
    $budget = Budget::factory()->create(['budget_type_id' => 'support']);

    $mutation = new BudgetMutation($budget);
    $mutation->usedCredit = BigDecimal::of('8');

    $usageService = mock(BudgetUsageService::class);
    $usageService->shouldReceive('get')->andReturn(new EloquentCollection([$mutation]));

    $ledgerMappings = new Collection([
        'project' => new LedgerMapping('project', '1001', '1002', '2001', '2002'),
    ]);

    $filePath = tempnam(sys_get_temp_dir(), 'exact').'.csv';
    (new BudgetsExactExport(2026, 6, $usageService, $ledgerMappings))->export($filePath);

    expect(file($filePath))->toHaveCount(1);
});

it('builds ledger mappings from the integration config', function () {
    $provider = ExactGlobeExportProvider::fromConfig([
        'ledger_mapping' => [
            'project' => [
                'verbruik_credit' => '1001',
                'verbruik_debit' => '1002',
                'vrijval_credit' => '2001',
                'vrijval_debit' => '2002',
            ],
        ],
    ]);

    expect($provider->exportFormats())->toHaveCount(1)
        ->and($provider->exportFormats()->first()->key)->toBe('exact-globe-csv');
});

it('exposes no export formats without a ledger mapping', function () {
    $provider = ExactGlobeExportProvider::fromConfig([]);

    expect($provider->exportFormats())->toBeEmpty();
});
