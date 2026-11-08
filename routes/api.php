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

use App\Http\Controllers\Auth\Token\IssueTokenController;
use App\Http\Controllers\Auth\Token\RevokeTokenController;
use App\Http\Controllers\Budgets\BudgetController;
use App\Http\Controllers\Budgets\BudgetShareLinkController;
use App\Http\Controllers\Budgets\BudgetTypeController;
use App\Http\Controllers\Budgets\GetBudgetPeriodsController;
use App\Http\Controllers\Budgets\GetBudgetTimeSpentTotalsController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\TrackedDomainController;
use App\Http\Controllers\Entries\CorrectionController;
use App\Http\Controllers\Entries\EntryController;
use App\Http\Controllers\Entries\MarkEntryAsInvoicedController;
use App\Http\Controllers\Exports\ExportEmailController;
use App\Http\Controllers\Exports\ExportFormatController;
use App\Http\Controllers\Exports\GetBudgetEntriesExportController;
use App\Http\Controllers\Integrations\GitRepositoryMappingController;
use App\Http\Controllers\Integrations\TicketController;
use App\Http\Controllers\Overtimes\ApproveOvertimeController;
use App\Http\Controllers\Overtimes\MarkOvertimeAsExportedController;
use App\Http\Controllers\Overtimes\OvertimeController;
use App\Http\Controllers\Reports\GetDailyProgressController;
use App\Http\Controllers\Reports\GetTimeSpentTotalsController;
use App\Http\Controllers\Reports\GetUserCustomerHoursAggregatesController;
use App\Http\Controllers\Suggestions\EntrySuggestionController;
use App\Http\Controllers\Suggestions\EventController;
use App\Http\Controllers\Suggestions\SuggestionIntegrationController;
use App\Http\Controllers\Users\PermissionController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\ShowCurrentUserController;
use App\Http\Controllers\Users\TeamController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\EncapsulateRequestBodyWithData;
use App\Http\Middleware\ImpersonateUsers;
use App\Http\Middleware\RestrictShareTokenToSharedBudget;
use Illuminate\Support\Facades\Route;

Route::post('oauth/token', IssueTokenController::class)
    ->middleware('throttle:10,1')
    ->name('oauth.token.store');

Route::delete('oauth/token', RevokeTokenController::class)
    ->middleware('auth:api')
    ->name('oauth.token.destroy');

Route::middleware([
    'auth:api,share_token',
    RestrictShareTokenToSharedBudget::class,
    EncapsulateRequestBodyWithData::class,
    ImpersonateUsers::class,
])->group(function () {
    Route::get('me', ShowCurrentUserController::class)->name('me');

    Route::get('daily-progress', GetDailyProgressController::class)->name('daily-progress');

    Route::get('export-formats', ExportFormatController::class)->name('export-formats.index');

    Route::get('budgets/export-mail', ExportEmailController::class)->name('budgets.export-mail');

    Route::apiResource('budgets', BudgetController::class);

    Route::get('budgets/{budget}/periods', GetBudgetPeriodsController::class)->name('budget.periods.show');
    Route::post('budgets/{budget}/share-link', [BudgetShareLinkController::class, 'store'])
        ->middleware('can:update,budget')
        ->name('budget.share-link.store');
    Route::delete('budgets/{budget}/share-link', [BudgetShareLinkController::class, 'destroy'])
        ->middleware('can:update,budget')
        ->name('budget.share-link.destroy');
    Route::get('budgets/{budget}/entries-export', GetBudgetEntriesExportController::class)->name('budget.entries-export');

    Route::apiResource('entry-suggestions', EntrySuggestionController::class)->except('store', 'update');

    Route::get('suggestion-integrations', SuggestionIntegrationController::class)->name('suggestion-integrations.index');

    Route::apiResource('entries', EntryController::class);
    Route::post('entries/{entry}/mark-as-invoiced', MarkEntryAsInvoicedController::class)
        ->middleware('can:entries.mark-as-invoiced')
        ->name('entry.mark-as-invoiced');

    Route::apiResource('overtimes', OvertimeController::class)->only('index');

    Route::post('overtimes/{overtime}/approve', ApproveOvertimeController::class)->name('overtime.approve');

    Route::post('overtimes/{overtime}/mark-as-exported', MarkOvertimeAsExportedController::class)
        ->middleware('can:overtimes.mark-as-exported')
        ->name('overtime.mark-as-exported');

    Route::apiResource('events', EventController::class)->only('store');

    Route::apiResource('budget-types', BudgetTypeController::class)->only('index');

    Route::apiResource('customers', CustomerController::class);

    Route::apiResource('users', UserController::class);

    Route::apiResource('teams', TeamController::class);

    Route::apiResource('permissions', PermissionController::class)->only('index', 'store');

    Route::apiResource('roles', RoleController::class)->only('index', 'store', 'update');

    Route::apiResource('corrections', CorrectionController::class)->only('store', 'update');

    Route::get('user-customer-hours-aggregates', GetUserCustomerHoursAggregatesController::class)
        ->name('user-customer-hours-aggregates');

    Route::get('time-spent-totals', GetTimeSpentTotalsController::class)
        ->name('time-spent-totals');

    Route::get('budget-time-spent-totals', GetBudgetTimeSpentTotalsController::class)->name('budget.time-spent-totals');

    Route::apiResource('tracked-domains', TrackedDomainController::class)->only(['index', 'store', 'destroy']);

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{key}', [TicketController::class, 'show'])->name('tickets.show');

    Route::get('git-repository-mappings', [GitRepositoryMappingController::class, 'index'])->name('git-repository-mappings.index');

});
