<?php

use App\Modules\Probes\Http\Controllers\ProbeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('probes')->name('probes.')->group(function () {
    Route::get('/', [ProbeController::class, 'index'])->name('index');
    Route::post('/', [ProbeController::class, 'store'])->name('store');
    Route::post('{probe}/enrollment-token', [ProbeController::class, 'enrollmentToken'])->name('enrollment-token');
    Route::delete('{probe}/token', [ProbeController::class, 'revoke'])->name('revoke');
    Route::delete('{probe}', [ProbeController::class, 'destroy'])->name('destroy');
});
