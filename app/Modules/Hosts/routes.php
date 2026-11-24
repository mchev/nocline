<?php

use App\Modules\Hosts\Http\Controllers\HostController;
use App\Modules\Hosts\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('hosts')->name('hosts.')->group(function () {
    Route::get('/', [HostController::class, 'index'])->name('index');
    Route::get('{host}', [HostController::class, 'show'])->name('show');
    Route::post('{host}/checks', [HostController::class, 'attachCheck'])->name('attach-check');
    Route::delete('{host}/checks/{check}', [HostController::class, 'detachCheck'])->name('detach-check');
    Route::post('/', [HostController::class, 'store'])->name('store');
    Route::patch('{host}', [HostController::class, 'update'])->name('update');
    Route::post('{host}/enrollment-token', [HostController::class, 'enrollmentToken'])->name('enrollment-token');
    Route::post('{host}/agent-update-command', [HostController::class, 'agentUpdateCommand'])->name('agent-update-command');
    Route::delete('{host}/token', [HostController::class, 'revoke'])->name('revoke');
    Route::delete('{host}', [HostController::class, 'destroy'])->name('destroy');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('install/agent-update.sh', [InstallController::class, 'agentUpdateScript'])->name('install.agent-update');
    Route::get('install/agent/{arch}', [InstallController::class, 'binary'])->name('install.binary');
    Route::get('install/{token}', [InstallController::class, 'script'])->name('install.script');
});
