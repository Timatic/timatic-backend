<?php

use Illuminate\Support\Facades\Route;
use Timatic\Jira\Http\Controllers\CallbackController;
use Timatic\Jira\Http\Controllers\DelegateController;
use Timatic\Jira\Http\Controllers\RedirectController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/jira/redirect', RedirectController::class)
        ->name('jira.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/jira/callback', CallbackController::class)
        ->name('jira.oauth.callback');

    Route::get('integrations/jira/connect/{token}', [DelegateController::class, 'show'])
        ->name('jira.delegate.show');
    Route::get('integrations/jira/connect/{token}/oauth-redirect', [DelegateController::class, 'oauthRedirect'])
        ->name('jira.delegate.oauth-redirect');
});
