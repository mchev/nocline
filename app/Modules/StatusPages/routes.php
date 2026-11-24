<?php

use App\Modules\StatusPages\Http\Controllers\PublicStatusPageController;
use App\Modules\StatusPages\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('status')->name('status.')->group(function () {
    Route::get('{slug}', [PublicStatusPageController::class, 'show'])->name('show');
    Route::get('{slug}/feed', [PublicStatusPageController::class, 'feed'])->name('feed');
});

Route::middleware(['auth', 'verified'])->prefix('status-pages')->name('status-pages.')->group(function () {
    Route::get('/', [StatusPageController::class, 'index'])->name('index');
    Route::post('/', [StatusPageController::class, 'store'])->name('store');
    Route::patch('{page}', [StatusPageController::class, 'update'])->name('update');
    Route::delete('{page}', [StatusPageController::class, 'destroy'])->name('destroy');
});
