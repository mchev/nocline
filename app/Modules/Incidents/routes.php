<?php

use App\Modules\Incidents\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('incidents')->name('incidents.')->group(function () {
    Route::get('/', [IncidentController::class, 'index'])->name('index');
    Route::post('{incident}/acknowledge', [IncidentController::class, 'acknowledge'])->name('acknowledge');
});
