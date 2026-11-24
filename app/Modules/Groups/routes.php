<?php

use App\Modules\Groups\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('groups')->name('groups.')->group(function () {
    Route::get('/', [GroupController::class, 'index'])->name('index');
    Route::post('/', [GroupController::class, 'store'])->name('store');
    Route::patch('{group}', [GroupController::class, 'update'])->name('update');
    Route::delete('{group}', [GroupController::class, 'destroy'])->name('destroy');
});
