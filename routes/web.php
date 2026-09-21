<?php

use App\Http\Controllers\Dashboard\BusinessContextController;
use App\Http\Middleware\ShareDashboardBusinessContext;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ShareDashboardBusinessContext::class])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('transactions', 'transactions.index')->name('transactions.index');
    Route::view('products', 'products.index')->name('products.index');
    Route::view('stock', 'stock.index')->name('stock.index');
    Route::view('cash', 'cash.index')->name('cash.index');
    Route::view('reports', 'reports.index')->name('reports.index');
    Route::view('devices', 'devices.index')->name('devices.index');
    Route::view('sync', 'sync.index')->name('sync.index');

    Route::post('dashboard/business-context', [BusinessContextController::class, 'update'])
        ->name('dashboard.business-context.update');
});

require __DIR__.'/settings.php';
