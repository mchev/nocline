<?php

use App\Modules\Alertes\Http\Controllers\ChannelController;
use App\Modules\Alertes\Http\Controllers\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('alertes/channels')->name('alertes.channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::post('/', [ChannelController::class, 'store'])->name('store');
    Route::post('{channel}/test', [ChannelController::class, 'test'])->name('test');
    Route::patch('{channel}', [ChannelController::class, 'update'])->name('update');
    Route::delete('{channel}', [ChannelController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'verified'])->prefix('alertes')->name('alertes.')->group(function () {
    Route::patch('notification-preferences', [NotificationPreferencesController::class, 'update'])
        ->name('notification-preferences.update');
});
