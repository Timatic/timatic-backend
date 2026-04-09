<?php

use Illuminate\Support\Facades\Route;
use Timatic\Bitbucket\Http\Controllers\CallbackController;
use Timatic\Bitbucket\Http\Controllers\DelegateController;
use Timatic\Bitbucket\Http\Controllers\RedirectController;
use Timatic\Bitbucket\Http\Controllers\WebhookController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/bitbucket/redirect', RedirectController::class)
        ->name('bitbucket.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/bitbucket/callback', CallbackController::class)
        ->name('bitbucket.oauth.callback');

    Route::post('integrations/bitbucket/webhook/{integration}', WebhookController::class)
        ->name('bitbucket.webhook');

    Route::get('integrations/bitbucket/connect/{token}', [DelegateController::class, 'show'])
        ->name('bitbucket.delegate.show');
    Route::get('integrations/bitbucket/connect/{token}/oauth-redirect', [DelegateController::class, 'oauthRedirect'])
        ->name('bitbucket.delegate.oauth-redirect');
    Route::post('integrations/bitbucket/connect/{token}/install-webhook', [DelegateController::class, 'installWebhook'])
        ->name('bitbucket.delegate.install-webhook');
});
