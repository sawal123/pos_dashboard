<?php

use App\Http\Controllers\Dashboard\BusinessContextController;
use App\Http\Controllers\Dashboard\CashController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\OutletsController;
use App\Http\Controllers\Dashboard\ProductsController;
use App\Http\Controllers\Dashboard\ReportsController;
use App\Http\Controllers\Dashboard\ShiftsController;
use App\Http\Controllers\Dashboard\StockController;
use App\Http\Controllers\Dashboard\SyncMonitoringController;
use App\Http\Controllers\Dashboard\TransactionsController;
use App\Http\Controllers\Dashboard\UsersController;
use App\Http\Middleware\ShareDashboardBusinessContext;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ShareDashboardBusinessContext::class])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('products', [ProductsController::class, 'index'])->name('products.index');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/{productId}/movements', [StockController::class, 'movements'])
        ->whereNumber('productId')
        ->name('stock.movements');
    Route::get('cash', [CashController::class, 'index'])->name('cash.index');
    Route::get('shifts', [ShiftsController::class, 'index'])->name('shifts.index');
    Route::get('shifts/{shiftId}/detail', [ShiftsController::class, 'detail'])
        ->whereNumber('shiftId')
        ->name('shifts.detail');
    Route::get('outlets', [OutletsController::class, 'index'])->name('outlets.index');
    Route::get('outlets/{outletId}/detail', [OutletsController::class, 'detail'])
        ->whereNumber('outletId')
        ->name('outlets.detail');
    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    Route::get('users/{userId}/detail', [UsersController::class, 'detail'])
        ->whereNumber('userId')
        ->name('users.detail');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');
    Route::get('sync', [SyncMonitoringController::class, 'index'])->name('sync.index');

    Route::post('dashboard/business-context', [BusinessContextController::class, 'update'])
        ->name('dashboard.business-context.update');
});

require __DIR__.'/settings.php';
