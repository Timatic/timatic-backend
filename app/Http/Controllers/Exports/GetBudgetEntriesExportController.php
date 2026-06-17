<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exports;

use App\Exports\BudgetEntriesExport;
use App\Http\Requests\EntryCollectionRequest;
use App\Models\Budget;
use App\Models\Entry;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GetBudgetEntriesExportController
{
    #[ExcludeRouteFromDocs]
    public function __invoke(
        Budget $budget,
        EntryCollectionRequest $request,
    ): Response {
        $entries = QueryBuilder::for(Entry::class)
            ->where('budget_id', $budget->id)
            // @phpstan-ignore-next-line
            ->allowedFilters([
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::exact('budgetId', 'budget_id'),
                'startedAt',
                'endedAt',
                AllowedFilter::exact('hasOvertime', 'has_overtime'),
                AllowedFilter::exact('userFullName', 'user_full_name'),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::exact('ticketNumber', 'ticket_number'),
                AllowedFilter::scope('settlement'),
                AllowedFilter::scope('isInvoiced'),
                AllowedFilter::scope('isInvoiceable'),
            ])
            ->allowedIncludes([
                'customer',
            ])
            ->get();

        $filename = sprintf('budget_%d_entries.xlsx', $budget->id);
        $filePath = storage_path("app/exports/{$filename}");

        $export = new BudgetEntriesExport($entries);
        $export->export($filePath);

        return response()->noContent();
    }
}
