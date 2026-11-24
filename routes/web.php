<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require app_path('Modules/Setup/routes.php');
require app_path('Modules/Groups/routes.php');
require app_path('Modules/Hosts/routes.php');
require app_path('Modules/Checks/routes.php');
require app_path('Modules/Incidents/routes.php');
require app_path('Modules/Alertes/routes.php');
require app_path('Modules/Probes/routes.php');
require app_path('Modules/Wallboard/routes.php');
require app_path('Modules/Coffre/routes.php');
require app_path('Modules/Renewals/routes.php');
require app_path('Modules/Users/routes.php');
require app_path('Modules/Api/routes.php');
require app_path('Modules/Audit/routes.php');
require app_path('Modules/Maintenance/routes.php');
require app_path('Modules/StatusPages/routes.php');
