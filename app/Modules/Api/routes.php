<?php

use App\Modules\Api\Http\Controllers\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('settings/api-tokens')->name('api-tokens.')->group(function () {
    Route::get('/', [ApiTokenController::class, 'index'])->name('index');
    Route::post('/', [ApiTokenController::class, 'store'])->name('store');
    Route::delete('{token}', [ApiTokenController::class, 'destroy'])->name('destroy');
});
