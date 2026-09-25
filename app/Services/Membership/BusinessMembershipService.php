<?php

namespace App\Services\Membership;

use App\Models\Business;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-driven membership management (DASH-10B1).
 *
 * Only `member` rows of the active business may be removed. The global user
 * account is never deleted and no tokens are revoked: dashboard and sync access
 * are denied per request through membership checks, so a user who still belongs
 * to another business keeps their legitimate access.
 */
class BusinessMembershipService
{
    public function __construct(
        private readonly MembershipAuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function removeMember(User $actor, Business $business, User $target): void
    {
        DB::transaction(function () use ($actor, $business, $target): void {
            $membership = DB::table('business_user')
                ->where('business_id', $business->id)
                ->where('user_id', $target->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages([
                    'member' => 'Pengguna ini bukan anggota bisnis aktif.',
                ]);
            }

            $role = (string) $membership->role;

            // An owner can never be removed here: the business must always keep
            // at least one owner, and ownership hand-over is out of scope.
            if ($role === Business::ROLE_OWNER) {
                throw ValidationException::withMessages([
                    'member' => 'Pemilik bisnis tidak dapat dihapus dari sini. Bisnis harus selalu memiliki minimal satu pemilik.',
                ]);
            }

            if ($role !== Business::ROLE_MEMBER) {
                throw ValidationException::withMessages([
                    'member' => 'Hanya anggota dengan peran Anggota yang dapat dihapus.',
                ]);
            }

            DB::table('business_user')
                ->where('business_id', $business->id)
                ->where('user_id', $target->id)
                ->delete();

            $this->audit->record(
                $business,
                $actor,
                MembershipAuditLog::ACTION_MEMBERSHIP_REMOVED,
                $target,
                $target->email,
                ['role' => $role],
            );
        });
    }
}
