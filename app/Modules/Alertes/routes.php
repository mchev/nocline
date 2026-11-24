<?php

use App\Modules\Alertes\Http\Controllers\ChannelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('alertes/channels')->name('alertes.channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::post('/', [ChannelController::class, 'store'])->name('store');
    Route::post('{channel}/test', [ChannelController::class, 'test'])->name('test');
    Route::patch('{channel}', [ChannelController::class, 'update'])->name('update');
    Route::delete('{channel}', [ChannelController::class, 'destroy'])->name('destroy');
});
