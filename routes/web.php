<?php

use App\Http\Controllers\Dashboard\BusinessContextController;
use App\Http\Controllers\Dashboard\BusinessInvitationsController;
use App\Http\Controllers\Dashboard\BusinessMembersController;
use App\Http\Controllers\Dashboard\CashController;
use App\Http\Controllers\Dashboard\CustomersController;
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
use App\Http\Controllers\Invitations\InvitationAcceptanceController;
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
    Route::get('customers', [CustomersController::class, 'index'])->name('customers.index');
    Route::get('customers/{customerId}/detail', [CustomersController::class, 'detail'])
        ->whereNumber('customerId')
        ->name('customers.detail');
    Route::get('outlets', [OutletsController::class, 'index'])->name('outlets.index');
    Route::get('outlets/{outletId}/detail', [OutletsController::class, 'detail'])
        ->whereNumber('outletId')
        ->name('outlets.detail');
    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    Route::get('users/{userId}/detail', [UsersController::class, 'detail'])
        ->whereNumber('userId')
        ->name('users.detail');

    // DASH-10B1 — owner-only invitation & membership management.
    Route::post('users/invitations', [BusinessInvitationsController::class, 'store'])
        ->middleware('throttle:member-invitations')
        ->name('users.invitations.store');
    Route::post('users/invitations/{invitation}/resend', [BusinessInvitationsController::class, 'resend'])
        ->middleware('throttle:member-invitation-resend')
        ->name('users.invitations.resend');
    Route::post('users/invitations/{invitation}/revoke', [BusinessInvitationsController::class, 'revoke'])
        ->middleware('throttle:member-invitation-revoke')
        ->name('users.invitations.revoke');
    Route::delete('users/members/{userId}', [BusinessMembersController::class, 'destroy'])
        ->whereNumber('userId')
        ->middleware('throttle:member-removal')
        ->name('users.members.destroy');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');
    Route::get('sync', [SyncMonitoringController::class, 'index'])->name('sync.index');

    Route::post('dashboard/business-context', [BusinessContextController::class, 'update'])
        ->name('dashboard.business-context.update');
});

// DASH-10B1 — invitation acceptance. GET is public so a brand-new user can be
// routed into Fortify registration; acceptance itself requires auth + verified.
Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('invitations.show');

Route::post('invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
    ->middleware(['auth', 'verified', 'throttle:member-invitation-accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('invitations.accept');

require __DIR__.'/settings.php';
