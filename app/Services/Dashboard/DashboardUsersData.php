<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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

    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

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
     * Tenant-scoped membership summary. Owner, member and other-role buckets are
     * mutually exclusive, so an unknown role is never folded into member.
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
            ->selectRaw("COALESCE(SUM(CASE WHEN business_user.role NOT IN ('owner', 'member') THEN 1 ELSE 0 END), 0) as other_role_count")
            ->selectRaw('COALESCE(SUM(CASE WHEN users.email_verified_at IS NOT NULL THEN 1 ELSE 0 END), 0) as verified_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN users.email_verified_at IS NULL THEN 1 ELSE 0 END), 0) as unverified_count')
            ->first();

        return [
            'total_members' => $row !== null ? (int) $row->total_members : 0,
            'owner_count' => $row !== null ? (int) $row->owner_count : 0,
            'member_count' => $row !== null ? (int) $row->member_count : 0,
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
     * Present an explicit role. Only `owner` and `member` have a proven
     * authorization contract; every other value is surfaced as an unmapped role
     * and never silently treated as a member or a cashier.
     */
    private function presentRole(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'Pemilik',
            self::ROLE_MEMBER => 'Anggota',
            '' => 'Tanpa Peran',
            default => ucwords(str_replace(['_', '-'], ' ', $role)),
        };
    }

    /**
     * @return 'owner'|'member'|'other'
     */
    private function roleCategory(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'owner',
            self::ROLE_MEMBER => 'member',
            default => 'other',
        };
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
                'other_role_count' => 0,
                'verified_count' => 0,
                'unverified_count' => 0,
            ],
            'filterOptions' => [
                'roles' => [],
            ],
            'currentFilters' => $currentFilters,
            'hasAnyMembers' => false,
        ];
    }
}
