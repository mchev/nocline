<?php

use App\Modules\Checks\Http\Controllers\HeartbeatController;
use App\Modules\Hosts\Http\Controllers\EnrollController;
use App\Modules\Hosts\Http\Middleware\AuthenticateAgent;
use App\Modules\Metrics\Http\Controllers\IngestController;
use App\Modules\Probes\Http\Controllers\ProbeResultsController;
use App\Modules\Probes\Http\Controllers\ProbeWorkController;
use App\Modules\Probes\Http\Middleware\AuthenticateProbe;
use App\Modules\Wallboard\Http\Controllers\WallboardController;
use Illuminate\Support\Facades\Route;

Route::get('wallboard/{token}', [WallboardController::class, 'data'])
    ->middleware('throttle:120,1')->name('api.wallboard');

Route::prefix('v1')->group(function () {
    Route::match(['get', 'post'], 'heartbeat/{token}/{outcome?}', HeartbeatController::class)
        ->whereIn('outcome', ['fail'])->middleware('throttle:120,1')->name('api.heartbeat');

    require app_path('Modules/Api/api.php');

    Route::post('enroll', EnrollController::class)->middleware('throttle:10,1')->name('api.enroll');

    Route::middleware([AuthenticateAgent::class, 'throttle:120,1'])->group(function () {
        Route::post('ingest', IngestController::class)->name('api.ingest');
    });

    Route::prefix('probe')->middleware([AuthenticateProbe::class, 'throttle:240,1'])->group(function () {
        Route::get('work', ProbeWorkController::class)->name('api.probe.work');
        Route::post('results', ProbeResultsController::class)->name('api.probe.results');
    });
});
