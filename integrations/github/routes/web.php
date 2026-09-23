<?php

use Illuminate\Support\Facades\Route;
use Timatic\GitHub\Http\Controllers\CallbackController;
use Timatic\GitHub\Http\Controllers\DelegateController;
use Timatic\GitHub\Http\Controllers\RedirectController;
use Timatic\GitHub\Http\Controllers\WebhookController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/github/redirect', RedirectController::class)
        ->name('github.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/github/callback', CallbackController::class)
        ->name('github.oauth.callback');

    Route::post('integrations/github/webhook/{integration}', WebhookController::class)
        ->name('github.webhook');

    Route::get('integrations/github/connect/{token}', [DelegateController::class, 'show'])
        ->name('github.delegate.show');
    Route::get('integrations/github/connect/{token}/oauth-redirect', [DelegateController::class, 'oauthRedirect'])
        ->name('github.delegate.oauth-redirect');
});
