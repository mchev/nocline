<?php

use App\Modules\Coffre\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('coffre')->name('coffre.')->group(function () {
    Route::get('/', [VaultController::class, 'index'])->name('index');
    Route::get('audit', [VaultController::class, 'audits'])->name('audit');
    Route::post('/', [VaultController::class, 'store'])->name('store');
    Route::patch('{entry}', [VaultController::class, 'update'])->name('update');
    Route::delete('{entry}', [VaultController::class, 'destroy'])->name('destroy');
    Route::post('{entry}/reveal', [VaultController::class, 'reveal'])->middleware('throttle:20,1')->name('reveal');
    Route::post('{entry}/copied', [VaultController::class, 'copied'])->name('copied');
});
