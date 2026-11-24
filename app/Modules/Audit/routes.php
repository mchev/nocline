<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('audit', [AuditLogController::class, 'index'])->name('audit.index');
