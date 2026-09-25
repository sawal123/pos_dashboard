<?php

use App\Http\Controllers\Dashboard\BusinessContextController;
use App\Http\Controllers\Dashboard\BusinessInvitationsController;
use App\Http\Controllers\Dashboard\BusinessMembersController;
use App\Http\Controllers\Dashboard\CashController;
use App\Http\Controllers\Dashboard\CustomersController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\LaundryOrdersController;
use App\Http\Controllers\Dashboard\OutletsController;
use App\Http\Controllers\Dashboard\ProductsController;
use App\Http\Controllers\Dashboard\ReportsController;
use App\Http\Controllers\Dashboard\ShiftsController;
use App\Http\Controllers\Dashboard\StockController;
use App\Http\Controllers\Dashboard\SubscriptionsController;
use App\Http\Controllers\Dashboard\SyncMonitoringController;
use App\Http\Controllers\Dashboard\TransactionsController;
use App\Http\Controllers\Dashboard\UsersController;
use App\Http\Controllers\Invitations\InvitationAcceptanceController;
use App\Http\Middleware\ShareDashboardBusinessContext;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ShareDashboardBusinessContext::class])->group(function () {
    // DASH-10B2 — every dashboard route carries an explicit, server-enforced
    // permission for the *active* business. Hiding a sidebar item is never the
    // only protection. See docs/dashboard/DASH10B2_CASHIER_RBAC.md.
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::DASHBOARD_VIEW)
        ->name('dashboard');

    Route::get('transactions', [TransactionsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::TRANSACTIONS_VIEW)
        ->name('transactions.index');

    Route::get('products', [ProductsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::PRODUCTS_VIEW)
        ->name('products.index');

    Route::get('stock', [StockController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::STOCK_VIEW)
        ->name('stock.index');
    Route::get('stock/{productId}/movements', [StockController::class, 'movements'])
        ->whereNumber('productId')
        ->middleware('business.permission:'.BusinessPermission::STOCK_VIEW)
        ->name('stock.movements');

    Route::get('cash', [CashController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::CASH_VIEW)
        ->name('cash.index');

    Route::get('shifts', [ShiftsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::SHIFTS_VIEW)
        ->name('shifts.index');
    Route::get('shifts/{shiftId}/detail', [ShiftsController::class, 'detail'])
        ->whereNumber('shiftId')
        ->middleware('business.permission:'.BusinessPermission::SHIFTS_VIEW)
        ->name('shifts.detail');

    Route::get('customers', [CustomersController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::CUSTOMERS_VIEW)
        ->name('customers.index');
    Route::get('customers/{customerId}/detail', [CustomersController::class, 'detail'])
        ->whereNumber('customerId')
        ->middleware('business.permission:'.BusinessPermission::CUSTOMERS_VIEW)
        ->name('customers.detail');

    Route::get('laundry-orders', [LaundryOrdersController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::LAUNDRY_VIEW)
        ->name('laundry-orders.index');
    Route::get('laundry-orders/{saleId}/detail', [LaundryOrdersController::class, 'detail'])
        ->whereNumber('saleId')
        ->middleware('business.permission:'.BusinessPermission::LAUNDRY_VIEW)
        ->name('laundry-orders.detail');

    Route::get('outlets', [OutletsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::OUTLETS_VIEW)
        ->name('outlets.index');
    Route::get('outlets/{outletId}/detail', [OutletsController::class, 'detail'])
        ->whereNumber('outletId')
        ->middleware('business.permission:'.BusinessPermission::OUTLETS_VIEW)
        ->name('outlets.detail');

    // Owner-only member administration.
    Route::get('users', [UsersController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::USERS_VIEW)
        ->name('users.index');
    Route::get('users/{userId}/detail', [UsersController::class, 'detail'])
        ->whereNumber('userId')
        ->middleware('business.permission:'.BusinessPermission::USERS_VIEW)
        ->name('users.detail');

    Route::post('users/invitations', [BusinessInvitationsController::class, 'store'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'throttle:member-invitations'])
        ->name('users.invitations.store');
    Route::post('users/invitations/{invitation}/resend', [BusinessInvitationsController::class, 'resend'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'throttle:member-invitation-resend'])
        ->name('users.invitations.resend');
    Route::post('users/invitations/{invitation}/revoke', [BusinessInvitationsController::class, 'revoke'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'throttle:member-invitation-revoke'])
        ->name('users.invitations.revoke');
    Route::delete('users/members/{userId}', [BusinessMembersController::class, 'destroy'])
        ->whereNumber('userId')
        ->middleware(['business.permission:'.BusinessPermission::MEMBERS_MANAGE, 'throttle:member-removal'])
        ->name('users.members.destroy');

    // DASH-10B2 — owner-only role change (member <-> cashier).
    Route::patch('users/members/{userId}/role', [BusinessMembersController::class, 'updateRole'])
        ->whereNumber('userId')
        ->middleware(['business.permission:'.BusinessPermission::ROLES_MANAGE, 'throttle:member-role-update'])
        ->name('users.members.role.update');

    Route::get('reports', [ReportsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::REPORTS_VIEW)
        ->name('reports.index');

    Route::get('devices', [DevicesController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::DEVICES_VIEW)
        ->name('devices.index');

    Route::get('sync', [SyncMonitoringController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::SYNC_VIEW)
        ->name('sync.index');

    // DASH-12A — subscription overview. Owner-only, aligned with the controller
    // authorization via the shared SUBSCRIPTION_MANAGE permission (DASH-10B2).
    Route::get('subscription', [SubscriptionsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::SUBSCRIPTION_MANAGE)
        ->name('subscriptions.index');

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
