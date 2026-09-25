<?php

namespace App\Services\Membership;

use App\Models\Business;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-driven membership management (DASH-10B1 / DASH-10B2).
 *
 * Only {@see Business::MANAGED_ROLES} (`member`, `cashier`) rows of the active
 * business may be removed or re-roled. An `owner` row is never touchable here:
 * the business must always keep at least one owner and ownership hand-over is
 * out of scope. The global user account is never deleted and no tokens are
 * revoked — dashboard and sync access are re-evaluated per request from the
 * membership row, so a role change takes effect on the next request even for an
 * already-issued Sanctum token.
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

            if (! in_array($role, Business::MANAGED_ROLES, true)) {
                throw ValidationException::withMessages([
                    'member' => 'Hanya anggota dengan peran Anggota atau Kasir yang dapat dihapus.',
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

    /**
     * Change the role of an existing member between the managed roles
     * (`member` <-> `cashier`). Runs in a transaction with a row lock so two
     * concurrent changes cannot interleave.
     *
     * @return string the new role
     *
     * @throws ValidationException
     */
    public function changeRole(User $actor, Business $business, User $target, string $newRole): string
    {
        /** @var string $result */
        $result = DB::transaction(function () use ($actor, $business, $target, $newRole): string {
            if ($target->is($actor)) {
                throw ValidationException::withMessages([
                    'role' => 'Anda tidak dapat mengubah peran Anda sendiri dari halaman ini.',
                ]);
            }

            if (! in_array($newRole, Business::MANAGED_ROLES, true)) {
                throw ValidationException::withMessages([
                    'role' => 'Peran tujuan tidak didukung. Pilih Anggota atau Kasir.',
                ]);
            }

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

            $oldRole = (string) $membership->role;

            if ($oldRole === Business::ROLE_OWNER) {
                throw ValidationException::withMessages([
                    'role' => 'Peran pemilik tidak dapat diubah dari halaman ini.',
                ]);
            }

            if (! in_array($oldRole, Business::MANAGED_ROLES, true)) {
                throw ValidationException::withMessages([
                    'role' => 'Peran anggota saat ini tidak dapat diubah dari halaman ini.',
                ]);
            }

            if ($oldRole === $newRole) {
                throw ValidationException::withMessages([
                    'role' => 'Pengguna sudah memiliki peran tersebut.',
                ]);
            }

            DB::table('business_user')
                ->where('business_id', $business->id)
                ->where('user_id', $target->id)
                ->update([
                    'role' => $newRole,
                    'updated_at' => now(),
                ]);

            $this->audit->record(
                $business,
                $actor,
                MembershipAuditLog::ACTION_ROLE_CHANGED,
                $target,
                $target->email,
                ['old_role' => $oldRole, 'new_role' => $newRole],
            );

            return $newRole;
        });

        return $result;
    }
}
