<?php

use Illuminate\Support\Facades\Route;
use Timatic\Nmbrs\Http\Controllers\CallbackController;
use Timatic\Nmbrs\Http\Controllers\RedirectController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/nmbrs/redirect', RedirectController::class)
        ->name('nmbrs.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/nmbrs/callback', CallbackController::class)
        ->name('nmbrs.oauth.callback');
});
