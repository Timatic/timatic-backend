<?php

use Illuminate\Support\Facades\Route;
use Timatic\GitHub\Http\Controllers\CallbackController;
use Timatic\GitHub\Http\Controllers\RedirectController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/github/redirect', RedirectController::class)
        ->name('github.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/github/callback', CallbackController::class)
        ->name('github.oauth.callback');
});
