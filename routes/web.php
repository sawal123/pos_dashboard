<?php

use App\Http\Controllers\Dashboard\BusinessContextController;
use App\Http\Controllers\Dashboard\BusinessInvitationsController;
use App\Http\Controllers\Dashboard\BusinessMembersController;
use App\Http\Controllers\Dashboard\BusinessSettingsController;
use App\Http\Controllers\Dashboard\CashController;
use App\Http\Controllers\Dashboard\CustomersController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\LaundryOrdersController;
use App\Http\Controllers\Dashboard\OutletsController;
use App\Http\Controllers\Dashboard\ProductsController;
use App\Http\Controllers\Dashboard\ReportExportController;
use App\Http\Controllers\Dashboard\ReportsController;
use App\Http\Controllers\Dashboard\ShiftsController;
use App\Http\Controllers\Dashboard\StockController;
use App\Http\Controllers\Dashboard\SubscriptionsController;
use App\Http\Controllers\Dashboard\SyncMonitoringController;
use App\Http\Controllers\Dashboard\TransactionsController;
use App\Http\Controllers\Dashboard\UsersController;
use App\Http\Controllers\Invitations\InvitationAcceptanceController;
use App\Http\Controllers\Platform\BusinessesController as PlatformBusinessesController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\SubscriptionsController as PlatformSubscriptionsController;
use App\Http\Controllers\Platform\UsersController as PlatformUsersController;
use App\Http\Middleware\ShareDashboardBusinessContext;
use App\Services\Authorization\BusinessPermission;
use App\Services\Subscription\PremiumPolicy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PREM-D02A — Premium (Cloud) entitlement gates
|--------------------------------------------------------------------------
|
| The server is the authoritative source of entitlement. RBAC
| (`business.permission:*`) is evaluated FIRST and entitlement
| (`premium.access:*`) second, so an unauthorized role still receives 403 and a
| non-entitled business receives the entitlement denial.
|
| Gated: the Premium dashboard surface (monitoring/data modules, cloud sync
| monitoring and the cloud device fleet).
|
| Deliberately NOT gated — the account/billing path an expired business still
| needs: `dashboard` (subscription state + upsell shell), `subscription`
| (status, and renewal once PREM-D02B ships), `business-settings`,
| `dashboard/business-context`, and the auth/profile routes in settings.php.
| A denied browser request is redirected back to `dashboard`, so an expired
| owner is never locked out of seeing their status or renewing.
*/
Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ShareDashboardBusinessContext::class])->group(function () {
    // DASH-10B2 — every dashboard route carries an explicit, server-enforced
    // permission for the *active* business. Hiding a sidebar item is never the
    // only protection. See docs/dashboard/DASH10B2_CASHIER_RBAC.md.
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::DASHBOARD_VIEW)
        ->name('dashboard');

    Route::get('transactions', [TransactionsController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::TRANSACTIONS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('transactions.index');

    Route::get('products', [ProductsController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.index');

    // DASH-15 — owner-only catalog management (products, services, categories).
    // Reading stays on products.view; every mutation requires products.manage.
    // Specific routes are registered before the generic {productId} route.
    Route::patch('products/{productId}/status', [ProductsController::class, 'updateStatus'])
        ->whereNumber('productId')
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.status.update');
    Route::patch('products/categories/{categoryId}/status', [ProductsController::class, 'updateCategoryStatus'])
        ->whereNumber('categoryId')
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.categories.status.update');
    Route::post('products/categories', [ProductsController::class, 'storeCategory'])
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.categories.store');
    Route::patch('products/categories/{categoryId}', [ProductsController::class, 'updateCategory'])
        ->whereNumber('categoryId')
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.categories.update');
    Route::post('products', [ProductsController::class, 'store'])
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.store');
    Route::patch('products/{productId}', [ProductsController::class, 'update'])
        ->whereNumber('productId')
        ->middleware(['business.permission:'.BusinessPermission::PRODUCTS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('products.update');

    Route::get('stock', [StockController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::STOCK_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('stock.index');
    Route::get('stock/{productId}/movements', [StockController::class, 'movements'])
        ->whereNumber('productId')
        ->middleware(['business.permission:'.BusinessPermission::STOCK_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('stock.movements');

    Route::get('cash', [CashController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::CASH_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('cash.index');
    Route::post('cash/ledger', [CashController::class, 'storeLedger'])
        ->middleware(['business.permission:'.BusinessPermission::CASH_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('cash.ledger.store');
    Route::post('cash/ledger/{cashLedger}/reverse', [CashController::class, 'reverseLedger'])
        ->whereNumber('cashLedger')
        ->middleware(['business.permission:'.BusinessPermission::CASH_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('cash.ledger.reverse');
    Route::post('cash/expenses', [CashController::class, 'storeExpense'])
        ->middleware(['business.permission:'.BusinessPermission::CASH_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('cash.expenses.store');
    Route::post('cash/expenses/{expense}/void', [CashController::class, 'voidExpense'])
        ->whereNumber('expense')
        ->middleware(['business.permission:'.BusinessPermission::CASH_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('cash.expenses.void');

    Route::get('shifts', [ShiftsController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::SHIFTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('shifts.index');
    Route::get('shifts/{shiftId}/detail', [ShiftsController::class, 'detail'])
        ->whereNumber('shiftId')
        ->middleware(['business.permission:'.BusinessPermission::SHIFTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('shifts.detail');

    Route::get('customers', [CustomersController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::CUSTOMERS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('customers.index');
    Route::get('customers/{customerId}/detail', [CustomersController::class, 'detail'])
        ->whereNumber('customerId')
        ->middleware(['business.permission:'.BusinessPermission::CUSTOMERS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('customers.detail');

    Route::get('laundry-orders', [LaundryOrdersController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::LAUNDRY_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('laundry-orders.index');
    Route::get('laundry-orders/{saleId}/detail', [LaundryOrdersController::class, 'detail'])
        ->whereNumber('saleId')
        ->middleware(['business.permission:'.BusinessPermission::LAUNDRY_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('laundry-orders.detail');

    Route::get('outlets', [OutletsController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::OUTLETS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('outlets.index');
    Route::get('outlets/{outletId}/detail', [OutletsController::class, 'detail'])
        ->whereNumber('outletId')
        ->middleware(['business.permission:'.BusinessPermission::OUTLETS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('outlets.detail');

    // Owner-only member administration.
    Route::get('users', [UsersController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::USERS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('users.index');
    Route::get('users/{userId}/detail', [UsersController::class, 'detail'])
        ->whereNumber('userId')
        ->middleware(['business.permission:'.BusinessPermission::USERS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('users.detail');

    Route::post('users/invitations', [BusinessInvitationsController::class, 'store'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:member-invitations'])
        ->name('users.invitations.store');
    Route::post('users/invitations/{invitation}/resend', [BusinessInvitationsController::class, 'resend'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:member-invitation-resend'])
        ->name('users.invitations.resend');
    Route::post('users/invitations/{invitation}/revoke', [BusinessInvitationsController::class, 'revoke'])
        ->middleware(['business.permission:'.BusinessPermission::INVITATIONS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:member-invitation-revoke'])
        ->name('users.invitations.revoke');
    Route::delete('users/members/{userId}', [BusinessMembersController::class, 'destroy'])
        ->whereNumber('userId')
        ->middleware(['business.permission:'.BusinessPermission::MEMBERS_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:member-removal'])
        ->name('users.members.destroy');

    // DASH-10B2 — owner-only role change (member <-> cashier).
    Route::patch('users/members/{userId}/role', [BusinessMembersController::class, 'updateRole'])
        ->whereNumber('userId')
        ->middleware(['business.permission:'.BusinessPermission::ROLES_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:member-role-update'])
        ->name('users.members.role.update');

    Route::get('reports', [ReportsController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::REPORTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('reports.index');

    // DASH-13 — report exports. Same reports.view permission as the page, so a
    // cashier (which has no reports.view) cannot download reports.
    Route::get('reports/export/csv', [ReportExportController::class, 'csv'])
        ->middleware(['business.permission:'.BusinessPermission::REPORTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('reports.export.csv');
    Route::get('reports/export/xlsx', [ReportExportController::class, 'xlsx'])
        ->middleware(['business.permission:'.BusinessPermission::REPORTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('reports.export.xlsx');
    Route::get('reports/export/pdf', [ReportExportController::class, 'pdf'])
        ->middleware(['business.permission:'.BusinessPermission::REPORTS_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
        ->name('reports.export.pdf');

    Route::get('devices', [DevicesController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::DEVICES_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_CLOUD_DEVICES])
        ->name('devices.index');

    // DASH-17 — owner-only device registration & management. Reading stays on
    // devices.view; every mutation requires devices.manage. The status route is
    // registered before the generic {deviceId} route.
    Route::patch('devices/{deviceId}/status', [DevicesController::class, 'updateStatus'])
        ->whereNumber('deviceId')
        ->middleware(['business.permission:'.BusinessPermission::DEVICES_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_CLOUD_DEVICES])
        ->name('devices.status.update');
    Route::post('devices', [DevicesController::class, 'store'])
        ->middleware(['business.permission:'.BusinessPermission::DEVICES_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_CLOUD_DEVICES])
        ->name('devices.store');
    Route::patch('devices/{deviceId}', [DevicesController::class, 'update'])
        ->whereNumber('deviceId')
        ->middleware(['business.permission:'.BusinessPermission::DEVICES_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_CLOUD_DEVICES])
        ->name('devices.update');

    Route::get('sync', [SyncMonitoringController::class, 'index'])
        ->middleware(['business.permission:'.BusinessPermission::SYNC_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_CLOUD_SYNC])
        ->name('sync.index');

    // DASH-12A — subscription overview. Owner-only, aligned with the controller
    // authorization via the shared SUBSCRIPTION_MANAGE permission (DASH-10B2).
    // PREM-D02A — intentionally NOT entitlement-gated: this is the billing/status
    // page an expired business must still reach in order to renew.
    Route::get('subscription', [SubscriptionsController::class, 'index'])
        ->middleware('business.permission:'.BusinessPermission::SUBSCRIPTION_MANAGE)
        ->name('subscriptions.index');

    // DASH-14 — owner-only business profile / business type setup.
    Route::get('business-settings', [BusinessSettingsController::class, 'edit'])
        ->middleware('business.permission:'.BusinessPermission::BUSINESS_SETTINGS_MANAGE)
        ->name('business-settings.edit');
    Route::patch('business-settings/business-type', [BusinessSettingsController::class, 'update'])
        ->middleware('business.permission:'.BusinessPermission::BUSINESS_SETTINGS_MANAGE)
        ->name('business-settings.business-type.update');

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

// ADMIN-01 — Platform Admin foundation.
// Completely decoupled from business context (ShareDashboardBusinessContext).
// Deny-by-default via EnsurePlatformAdmin (platform.admin).
Route::prefix('platform')
    ->middleware(['auth', 'verified', 'platform.admin'])
    ->name('platform.')
    ->group(function () {
        Route::get('/', [PlatformDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard.alias');

        // ADMIN-03 — Business / Merchant Management
        Route::get('businesses', [PlatformBusinessesController::class, 'index'])->name('businesses.index');
        Route::get('businesses/{business}', [PlatformBusinessesController::class, 'show'])->name('businesses.show');
        Route::patch('businesses/{business}/status', [PlatformBusinessesController::class, 'updateStatus'])->name('businesses.status.update');

        // ADMIN-04 — Platform User Management
        Route::get('users', [PlatformUsersController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [PlatformUsersController::class, 'show'])->name('users.show');

        // ADMIN-05 — Subscription & Plan Management
        Route::get('subscriptions', [PlatformSubscriptionsController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [PlatformSubscriptionsController::class, 'show'])->name('subscriptions.show');
        Route::patch('subscriptions/{subscription}/activate', [PlatformSubscriptionsController::class, 'activate'])->name('subscriptions.activate');
        Route::post('subscriptions/{subscription}/renew', [PlatformSubscriptionsController::class, 'renew'])->name('subscriptions.renew');
        Route::patch('subscriptions/{subscription}/downgrade', [PlatformSubscriptionsController::class, 'downgrade'])->name('subscriptions.downgrade');
        Route::patch('subscriptions/{subscription}/inactivate', [PlatformSubscriptionsController::class, 'inactivate'])->name('subscriptions.inactivate');
    });

require __DIR__.'/settings.php';
