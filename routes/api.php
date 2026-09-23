<?php

/*
|--------------------------------------------------------------------------
| Api Routes
|--------------------------------------------------------------------------
|
| The json:api. These routes run on the api middleware group, so no session is
| started, no cookie is read or written and callers authenticate with a bearer
| token or an api key.
|
*/

use App\Http\Controllers\Auth\ShowProviderController;
use App\Http\Controllers\Auth\Token\IssueTokenController;
use App\Http\Controllers\Auth\Token\RevokeTokenController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetTypeController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\EntrySuggestionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportEmailController;
use App\Http\Controllers\ExportFormatController;
use App\Http\Controllers\Exports\GetBudgetEntriesExportController;
use App\Http\Controllers\GetBudgetPeriodsController;
use App\Http\Controllers\GetBudgetTimeSpentTotalsController;
use App\Http\Controllers\GetDailyProgressController;
use App\Http\Controllers\GetTimeSpentTotalsController;
use App\Http\Controllers\GetUserCustomerHoursAggregatesController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\ShowCurrentUserController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TrackedDomainController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EncapsulateRequestBodyWithData;
use App\Http\Middleware\ImpersonateUsers;
use Illuminate\Support\Facades\Route;

Route::get('auth/provider', ShowProviderController::class)->name('auth.provider');

Route::post('oauth/token', IssueTokenController::class)
    ->middleware('throttle:10,1')
    ->name('oauth.token.store');

Route::delete('oauth/token', RevokeTokenController::class)
    ->middleware('auth:api')
    ->name('oauth.token.destroy');

Route::middleware([
    'auth:api',
    EncapsulateRequestBodyWithData::class,
    ImpersonateUsers::class,
])->group(function () {
    Route::get('me', ShowCurrentUserController::class)->name('me');

    Route::get('daily-progress', GetDailyProgressController::class)->name('daily-progress');

    Route::get('export-formats', ExportFormatController::class)->name('export-formats.index');

    Route::get('budgets/export-mail', ExportEmailController::class)->name('budgets.export-mail');

    Route::apiResource('budgets', BudgetController::class);

    Route::get('budgets/{budget}/periods', GetBudgetPeriodsController::class)->name('budget.periods.show');
    Route::get('budgets/{budget}/entries-export', GetBudgetEntriesExportController::class)->name('budget.entries-export');

    Route::apiResource('entry-suggestions', EntrySuggestionController::class)->except('store', 'update');

    Route::apiResource('entries', EntryController::class);
    Route::post('entries/{entry}/mark-as-invoiced', [EntryController::class, 'markAsInvoiced'])->name('entry.mark-as-invoiced');

    Route::apiResource('overtimes', OvertimeController::class)->only('index');

    Route::post('overtimes/{overtime}/approve', [OvertimeController::class, 'approve'])->name('overtime.approve');

    Route::post('overtimes/{overtime}/mark-as-exported', [OvertimeController::class, 'markAsExported'])->name('overtime.mark-as-exported');

    Route::apiResource('events', EventController::class)->only('store');

    Route::apiResource('budget-types', BudgetTypeController::class)->only('index');

    Route::apiResource('customers', CustomerController::class);

    Route::apiResource('users', UserController::class);

    Route::apiResource('teams', TeamController::class);

    Route::apiResource('corrections', CorrectionController::class)->only('store', 'update');

    Route::get('user-customer-hours-aggregates', GetUserCustomerHoursAggregatesController::class)
        ->name('user-customer-hours-aggregates');

    Route::get('time-spent-totals', GetTimeSpentTotalsController::class)
        ->name('time-spent-totals');

    Route::get('budget-time-spent-totals', GetBudgetTimeSpentTotalsController::class)->name('budget.time-spent-totals');

    Route::apiResource('tracked-domains', TrackedDomainController::class)->only(['index', 'store', 'destroy']);

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{key}', [TicketController::class, 'show'])->name('tickets.show');

});
