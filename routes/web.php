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
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RedirectController;
use App\Http\Controllers\Auth\Token\ApproveAuthorizationController;
use App\Http\Controllers\Auth\Token\AuthorizeController;
use App\Http\Controllers\DownloadExportController;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/download-export/{fileName}', DownloadExportController::class)->name('download.export');

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
Route::get('auth/logout', LogoutController::class)->name('auth.logout');

Route::middleware('auth:web')->group(function () {
    Route::get('oauth/authorize', [AuthorizeController::class, 'show'])->name('oauth.authorize.show');

    Route::post('oauth/authorize', ApproveAuthorizationController::class)
        ->middleware('signed')
        ->name('oauth.authorize.approve');
});
