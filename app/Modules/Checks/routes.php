<?php

use App\Modules\Checks\Http\Controllers\CheckController;
use App\Modules\Checks\Http\Controllers\MonitorController;
use App\Modules\Checks\Http\Controllers\SiteDetectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('checks')->name('checks.')->group(function () {
    Route::get('/', [CheckController::class, 'index'])->name('index');
    Route::post('/', [CheckController::class, 'store'])->name('store');
    Route::post('import/kuma', [CheckController::class, 'importKuma'])->name('import-kuma');
    Route::post('bulk', [CheckController::class, 'bulk'])->name('bulk');
    Route::patch('{check}', [CheckController::class, 'update'])->name('update');
    Route::delete('{check}', [CheckController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'verified'])->prefix('monitor')->name('monitor.')->group(function () {
    Route::get('new', [MonitorController::class, 'create'])->name('create');
    Route::post('detect', SiteDetectionController::class)->middleware('throttle:20,1')->name('detect');
    Route::post('website', [MonitorController::class, 'website'])->name('website');
    Route::post('server', [MonitorController::class, 'server'])->name('server');
    Route::post('service', [MonitorController::class, 'service'])->name('service');
    Route::post('heartbeat', [MonitorController::class, 'heartbeat'])->name('heartbeat.store');
    Route::get('heartbeats/{check}', [MonitorController::class, 'heartbeatInstall'])->name('heartbeat');
    Route::get('servers/{host}/install', [MonitorController::class, 'install'])->name('install');
    Route::post('servers/{host}/install', [MonitorController::class, 'regenerate'])->name('regenerate');
});
