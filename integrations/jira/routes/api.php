<?php

use Illuminate\Support\Facades\Route;
use Timatic\Jira\Http\Controllers\ProjectMappingController;

Route::middleware(['api', 'auth:api'])->group(function () {
    Route::get('jira/project-mappings', [ProjectMappingController::class, 'index'])
        ->name('jira.project-mappings.index');
});
