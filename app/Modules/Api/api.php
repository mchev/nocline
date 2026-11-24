<?php

use App\Modules\Api\Http\Controllers\V1\CheckController;
use App\Modules\Api\Http\Controllers\V1\GroupController;
use App\Modules\Api\Http\Controllers\V1\HostController;
use App\Modules\Api\Http\Controllers\V1\MeController;
use App\Modules\Api\Http\Controllers\V1\WebsiteMonitorController;
use Illuminate\Support\Facades\Route;

/*
 * Authenticated REST API (personal API tokens). Included inside the `api/v1` group of routes/api.php.
 */
Route::middleware('auth.api-token')->name('api.v1.')->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::get('checks/{check}/status', [CheckController::class, 'status'])->name('checks.status');
    Route::apiResource('checks', CheckController::class);
    Route::apiResource('groups', GroupController::class);
    Route::get('hosts', [HostController::class, 'index'])->name('hosts.index');
    Route::post('monitors/website', WebsiteMonitorController::class)->name('monitors.website');
});
