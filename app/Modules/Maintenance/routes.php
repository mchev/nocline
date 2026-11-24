<?php

use App\Modules\Maintenance\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('maintenance')->name('maintenance.')->group(function () {
    Route::get('/', [MaintenanceController::class, 'index'])->name('index');
    Route::post('/', [MaintenanceController::class, 'store'])->name('store');
    Route::patch('{window}', [MaintenanceController::class, 'update'])->name('update');
    Route::delete('{window}', [MaintenanceController::class, 'destroy'])->name('destroy');
});
