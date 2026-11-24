<?php

use App\Modules\Renewals\Http\Controllers\RenewalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('renewals')->name('renewals.')->group(function () {
    Route::get('/', [RenewalController::class, 'index'])->name('index');
    Route::post('refresh', [RenewalController::class, 'refresh'])->middleware('throttle:3,1')->name('refresh');
});
