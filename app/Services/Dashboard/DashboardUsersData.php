<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, tenant-scoped business member monitoring data.
 *
 * Every metric is derived from `business_user` membership rows belonging to the
 * current business only, joined to the matching `users` rows for verification
 * state. There is deliberately no online/activity metric because no heartbeat
 * source exists, and no cashier metric because shifts and sales carry no
 * operator relation.
 */
class DashboardUsersData
{
    public const PER_PAGE = 25;

    /** Maximum number of invitations rendered on the users page. */
    public const INVITATIONS_LIMIT = 50;

    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

    public const ROLE_CASHIER = 'cashier';

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = $this->sanitizeFilters($filters);

        if ($currentBusiness === null) {
            return $this->emptyResult($currentFilters);
        }

        $businessId = (int) $currentBusiness->id;

        $summary = $this->summary($businessId);

        $roles = DB::table('business_user')
            ->where('business_id', $businessId)
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->map(fn (string $role): array => [
                'value' => $role,
                'label' => $this->presentRole($role),
            ])
            ->values()
            ->toArray();

        $query = $this->baseQuery($businessId);
        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = $query
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (User $user): array => $this->presentMember($user));

        return [
            'users' => $paginator,
            'summary' => $summary,
            'filterOptions' => [
                'roles' => $roles,
            ],
            'roleOptions' => $this->roleOptions(),
            'currentFilters' => $currentFilters,
            'hasAnyMembers' => $summary['total_members'] > 0,
        ];
    }

    /**
     * Read-only detail for a single member of the current business. A foreign or
     * unknown user id aborts with 404. Only membership data for the current
     * business is exposed; sensitive user attributes are never included.
     *
     * @return array<string, mixed>
     */
    public function detail(Business $currentBusiness, int $userId): array
    {
        /** @var User|null $member */
        $member = $this->baseQuery((int) $currentBusiness->id)
            ->where('users.id', $userId)
            ->first();

        if ($member === null) {
            abort(404);
        }

        return $this->presentMember($member);
    }

    /**
     * Tenant-scoped invitations for the active business, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function invitations(Business $currentBusiness): Collection
    {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = BusinessInvitation::query()
            ->forBusiness((int) $currentBusiness->id)
            ->with('inviter')
            ->orderByDesc('id')
            ->limit(self::INVITATIONS_LIMIT)
            ->get()
            ->map(function (BusinessInvitation $invitation): array {
                $inviter = $invitation->getRelation('inviter');

                return [
                    'id' => (int) $invitation->id,
                    'email' => (string) $invitation->email,
                    'role' => $invitation->roleLabel(),
                    'role_raw' => (string) $invitation->role,
                    'status' => $invitation->effectiveStatus(),
                    'status_label' => $invitation->statusLabel(),
                    'is_pending' => $invitation->effectiveStatus() === BusinessInvitation::STATUS_PENDING,
                    'expires_at' => $this->formatDateTime($invitation->expires_at),
                    'expires_at_raw' => $invitation->expires_at?->format('Y-m-d H:i:s'),
                    'created_at' => $this->formatDateTime($invitation->created_at),
                    'invited_by' => $inviter instanceof User ? (string) $inviter->name : '',
                ];
            });

        return $rows;
    }

    /**
     * Invitation counts by effective status for the active business.
     *
     * @return array{pending: int, accepted: int, expired: int, revoked: int}
     */
    public function invitationSummary(Business $currentBusiness): array
    {
        $counts = [
            BusinessInvitation::STATUS_PENDING => 0,
            BusinessInvitation::STATUS_ACCEPTED => 0,
            BusinessInvitation::STATUS_EXPIRED => 0,
            BusinessInvitation::STATUS_REVOKED => 0,
        ];

        BusinessInvitation::query()
            ->forBusiness((int) $currentBusiness->id)
            ->get(['status', 'expires_at'])
            ->each(function (BusinessInvitation $invitation) use (&$counts): void {
                $status = $invitation->effectiveStatus();
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            });

        return [
            'pending' => $counts[BusinessInvitation::STATUS_PENDING],
            'accepted' => $counts[BusinessInvitation::STATUS_ACCEPTED],
            'expired' => $counts[BusinessInvitation::STATUS_EXPIRED],
            'revoked' => $counts[BusinessInvitation::STATUS_REVOKED],
        ];
    }

    /**
     * Tenant-scoped membership summary. Owner, member, cashier and other-role
     * buckets are mutually exclusive, so an unknown role is never folded into a
     * known role.
     *
     * @return array<string, int>
     */
    private function summary(int $businessId): array
    {
        $row = DB::table('business_user')
            ->join('users', 'users.id', '=', 'business_user.user_id')
            ->where('business_user.business_id', $businessId)
            ->selectRaw('COUNT(*) as total_members')
            ->selectRaw("COALESCE(SUM(CASE WHEN business_user.role = 'owner' THEN 1 ELSE 0 END), 0) as owner_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN business_user.role = 'member' THEN 1 ELSE 0 END), 0) as member_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN business_user.role = 'cashier' THEN 1 ELSE 0 END), 0) as cashier_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN business_user.role NOT IN ('owner', 'member', 'cashier') THEN 1 ELSE 0 END), 0) as other_role_count")
            ->selectRaw('COALESCE(SUM(CASE WHEN users.email_verified_at IS NOT NULL THEN 1 ELSE 0 END), 0) as verified_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN users.email_verified_at IS NULL THEN 1 ELSE 0 END), 0) as unverified_count')
            ->first();

        return [
            'total_members' => $row !== null ? (int) $row->total_members : 0,
            'owner_count' => $row !== null ? (int) $row->owner_count : 0,
            'member_count' => $row !== null ? (int) $row->member_count : 0,
            'cashier_count' => $row !== null ? (int) $row->cashier_count : 0,
            'other_role_count' => $row !== null ? (int) $row->other_role_count : 0,
            'verified_count' => $row !== null ? (int) $row->verified_count : 0,
            'unverified_count' => $row !== null ? (int) $row->unverified_count : 0,
        ];
    }

    /**
     * Membership-scoped user query. Selecting explicit columns keeps the pivot
     * role and join date available without an extra query and never exposes the
     * password, tokens or two-factor columns.
     *
     * @return Builder<User>
     */
    private function baseQuery(int $businessId): Builder
    {
        return User::query()
            ->join('business_user', 'business_user.user_id', '=', 'users.id')
            ->where('business_user.business_id', $businessId)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.email_verified_at',
                'users.created_at',
                'business_user.role as membership_role',
                'business_user.created_at as membership_created_at',
            ]);
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $search = (string) $filters['q'];
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        if ($filters['role'] !== 'all') {
            $query->where('business_user.role', (string) $filters['role']);
        }

        if ($filters['verification'] === 'verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($filters['verification'] === 'unverified') {
            $query->whereNull('users.email_verified_at');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMember(User $user): array
    {
        $roleAttribute = $user->getAttribute('membership_role');
        $roleRaw = is_string($roleAttribute) ? $roleAttribute : '';

        $joinedAt = $user->getAttribute('membership_created_at');
        $createdAt = $user->created_at;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role_raw' => $roleRaw,
            'role' => $this->presentRole($roleRaw),
            'role_category' => $this->roleCategory($roleRaw),
            'is_verified' => $user->email_verified_at !== null,
            'joined_at_raw' => $joinedAt !== null ? (string) $joinedAt : null,
            'joined_at' => $this->formatDateTime($joinedAt),
            'account_created_at_raw' => $createdAt?->format('Y-m-d H:i:s'),
            'account_created_at' => $this->formatDateTime($createdAt),
        ];
    }

    /**
     * Present an explicit role. `owner`, `member` and `cashier` have a defined
     * authorization contract; every other value is surfaced verbatim and never
     * silently treated as a known role.
     */
    private function presentRole(string $role): string
    {
        return Business::roleLabel($role);
    }

    /**
     * @return 'owner'|'member'|'cashier'|'other'
     */
    private function roleCategory(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'owner',
            self::ROLE_MEMBER => 'member',
            self::ROLE_CASHIER => 'cashier',
            default => 'other',
        };
    }

    /**
     * Roles an owner can assign through the "Ubah Peran" flow, with labels.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (string $role): array => [
                'value' => $role,
                'label' => Business::roleLabel($role),
            ],
            Business::MANAGED_ROLES,
        );
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = Carbon::parse((string) $value);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y - H:i');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        $verification = isset($filters['verification']) ? (string) $filters['verification'] : '';

        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'role' => isset($filters['role']) && $filters['role'] !== '' && $filters['role'] !== 'all'
                ? (string) $filters['role']
                : 'all',
            'verification' => in_array($verification, ['verified', 'unverified'], true)
                ? $verification
                : 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $currentFilters
     * @return array<string, mixed>
     */
    private function emptyResult(array $currentFilters): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator([], 0, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);

        return [
            'users' => $empty,
            'summary' => [
                'total_members' => 0,
                'owner_count' => 0,
                'member_count' => 0,
                'cashier_count' => 0,
                'other_role_count' => 0,
                'verified_count' => 0,
                'unverified_count' => 0,
            ],
            'filterOptions' => [
                'roles' => [],
            ],
            'roleOptions' => $this->roleOptions(),
            'currentFilters' => $currentFilters,
            'hasAnyMembers' => false,
        ];
    }
}
