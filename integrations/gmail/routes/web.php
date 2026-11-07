<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Timatic\Gmail\Http\Controllers\CallbackController;
use Timatic\Gmail\Http\Controllers\ConnectController;

/*
 * The frontend holds a bearer token that a browser redirect cannot carry, so the connect url is
 * signed for the user instead and the callback finds that user again through the oauth state.
 */
Route::get('gmail/connect/{user}', ConnectController::class)
    ->middleware(['signed', SubstituteBindings::class])
    ->name('gmail.connect');

Route::get('gmail/callback', CallbackController::class)->name('gmail.callback');
