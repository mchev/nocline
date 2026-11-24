<?php

use App\Modules\Wallboard\Http\Controllers\WallboardController;
use Illuminate\Support\Facades\Route;

Route::get('wallboard/{token}', [WallboardController::class, 'show'])
    ->middleware('throttle:60,1')->name('wallboard.show');

Route::middleware(['auth', 'verified'])->prefix('wallboards')->name('wallboards.')->group(function () {
    Route::get('/', [WallboardController::class, 'index'])->name('index');
    Route::post('/', [WallboardController::class, 'store'])->name('store');
    Route::post('{wallboard}/regenerate', [WallboardController::class, 'regenerate'])->name('regenerate');
    Route::delete('{wallboard}', [WallboardController::class, 'destroy'])->name('destroy');
});
