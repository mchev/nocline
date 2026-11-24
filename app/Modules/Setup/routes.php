<?php

use App\Modules\Setup\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');
Route::post('setup/locale', [SetupController::class, 'locale'])->name('setup.locale');
Route::post('setup/test-connection', [SetupController::class, 'testConnection'])->middleware('throttle:20,1')->name('setup.test-connection');
