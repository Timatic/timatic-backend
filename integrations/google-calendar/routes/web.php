<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Timatic\GoogleCalendar\Http\Controllers\CallbackController;
use Timatic\GoogleCalendar\Http\Controllers\ConnectController;

/*
 * The frontend holds a bearer token that a browser redirect cannot carry, so the connect url is
 * signed for the user instead and the callback finds that user again through the oauth state.
 */
Route::get('google-calendar/connect/{user}', ConnectController::class)
    ->middleware(['signed', SubstituteBindings::class])
    ->name('google_calendar.connect');

Route::get('google-calendar/callback', CallbackController::class)->name('google_calendar.callback');
