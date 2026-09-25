<?php

namespace App\Services\Authorization;

use App\Models\Business;

/**
 * The single source of truth for the DASH-10B2 permission matrix.
 *
 * Permissions are evaluated per business + role, never globally. An unknown
 * role (or no membership) has no permissions at all — the matrix is
 * deny-by-default, and `owner` is the only role with the `*` wildcard so a
 * business owner can never lose access to a newly added capability.
 *
 * See docs/dashboard/DASH10B2_CASHIER_RBAC.md for the full route/endpoint map.
 */
final class BusinessPermission
{
    // Dashboard (read-only monitoring pages).
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const TRANSACTIONS_VIEW = 'transactions.view';

    public const PRODUCTS_VIEW = 'products.view';

    public const STOCK_VIEW = 'stock.view';

    public const CASH_VIEW = 'cash.view';

    public const SHIFTS_VIEW = 'shifts.view';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const LAUNDRY_VIEW = 'laundry.view';

    public const REPORTS_VIEW = 'reports.view';

    public const OUTLETS_VIEW = 'outlets.view';

    public const USERS_VIEW = 'users.view';

    // Membership administration (owner only).
    public const INVITATIONS_MANAGE = 'invitations.manage';

    public const MEMBERS_MANAGE = 'members.manage';

    public const ROLES_MANAGE = 'roles.manage';

    // Business/system administration (owner only).
    public const DEVICES_VIEW = 'devices.view';

    public const SYNC_VIEW = 'sync.view';

    public const SUBSCRIPTION_MANAGE = 'subscription.manage';

    // Mobile/sync API. Cashier is intentionally denied: the sync contract is
    // not cashier-safe yet (see DASH10B2 doc).
    public const SYNC_PUSH = 'sync.push';

    public const SYNC_PULL = 'sync.pull';

    public const MOBILE_DEVICES_MANAGE = 'mobile.devices.manage';

    /**
     * Role => permission list. Any role not listed here has zero permissions.
     *
     * @return array<string, list<string>>
     */
    private static function matrix(): array
    {
        return [
            Business::ROLE_OWNER => ['*'],
            Business::ROLE_MEMBER => [
                self::DASHBOARD_VIEW,
                self::TRANSACTIONS_VIEW,
                self::PRODUCTS_VIEW,
                self::STOCK_VIEW,
                self::CASH_VIEW,
                self::SHIFTS_VIEW,
                self::CUSTOMERS_VIEW,
                self::LAUNDRY_VIEW,
                self::REPORTS_VIEW,
                self::OUTLETS_VIEW,
                self::SYNC_PUSH,
                self::SYNC_PULL,
                self::MOBILE_DEVICES_MANAGE,
            ],
            Business::ROLE_CASHIER => [
                self::DASHBOARD_VIEW,
                self::TRANSACTIONS_VIEW,
                self::PRODUCTS_VIEW,
                self::STOCK_VIEW,
                self::CASH_VIEW,
                self::SHIFTS_VIEW,
                self::CUSTOMERS_VIEW,
                self::LAUNDRY_VIEW,
            ],
        ];
    }

    /**
     * Permissions granted to a role. `null` (no membership) and unknown roles
     * resolve to an empty list.
     *
     * @return list<string>
     */
    public static function forRole(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        return self::matrix()[$role] ?? [];
    }

    /**
     * Whether a role grants the given permission.
     */
    public static function roleAllows(?string $role, string $permission): bool
    {
        $permissions = self::forRole($role);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    /**
     * Every role that has an explicit entry in the matrix.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_keys(self::matrix());
    }
}
