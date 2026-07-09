<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\Auth\HandleCallbackController;
use App\Http\Controllers\Auth\RedirectController;
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
use App\Http\Controllers\UserController;
use App\Http\Middleware\EncapsulateRequestBodyWithData;
use App\Http\Middleware\ImpersonateUsers;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/download-export/{fileName}', [ExportEmailController::class, 'download'])->name('download.export');

Route::get('/docs', function () {
    $spec = app()->environment('local')
        ? app(Generator::class)()
        : file_get_contents(public_path('api.json'));

    return view('scramble::docs', [
        'spec' => is_array($spec) ? json_encode($spec) : $spec,
        'config' => Scramble::getGeneratorConfig('default'),
    ]);
})->middleware(config('scramble.middleware'));

Route::get('auth/redirect', RedirectController::class)->name('auth.redirect');
Route::get('auth/callback', HandleCallbackController::class)->name('auth.callback');

Route::middleware([
    'api',
    'auth:api,web',
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

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{key}', [TicketController::class, 'show'])->name('tickets.show');

});
