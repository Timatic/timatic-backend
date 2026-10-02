<?php

use Illuminate\Support\Facades\Route;
use Timatic\GoogleCalendar\Http\Controllers\CallbackController;
use Timatic\GoogleCalendar\Http\Controllers\ConnectController;

/*
 * Calendar access is granted per user rather than per integration, so both ends of the flow run on
 * the browser session of the user doing the granting.
 */
Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('google-calendar/connect', ConnectController::class)->name('google_calendar.connect');
    Route::get('google-calendar/callback', CallbackController::class)->name('google_calendar.callback');
});
