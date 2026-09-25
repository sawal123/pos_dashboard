<?php

namespace App\Services\Membership;

use App\Mail\BusinessInvitationMail;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owner-driven member invitation lifecycle (DASH-10B1).
 *
 * Invariants enforced here:
 *  - Only the `member` role may be invited.
 *  - A token is random, stored hashed (sha256), single-use, time-limited and
 *    revocable; the plaintext value is only ever handed to the mailer.
 *  - At most one pending invitation per business + email (database-backed).
 *  - Email is dispatched only after the database transaction has committed.
 */
class BusinessInvitationService
{
    public function __construct(
        private readonly MembershipAuditLogger $audit,
    ) {}

    /**
     * Create a pending invitation for the given email.
     *
     * @return array{invitation: BusinessInvitation, token: string}
     *
     * @throws ValidationException
     */
    public function invite(
        User $actor,
        Business $business,
        string $email,
        string $role = BusinessInvitation::ROLE_MEMBER,
    ): array {
        $email = Str::lower(trim($email));
        $this->assertRoleAllowed($role);

        if ($this->emailAlreadyMember($business, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah menjadi anggota bisnis aktif.',
            ]);
        }

        /** @var array{0: BusinessInvitation, 1: string} $result */
        $result = DB::transaction(function () use ($actor, $business, $email, $role): array {
            $this->expireStaleInvitations($business, $email);

            $existing = BusinessInvitation::query()
                ->forBusiness((int) $business->id)
                ->where('email', $email)
                ->where('status', BusinessInvitation::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'email' => 'Sudah ada undangan yang menunggu untuk email ini. Kirim ulang atau batalkan dulu.',
                ]);
            }

            $token = BusinessInvitation::generateToken();

            $invitation = BusinessInvitation::create([
                'business_id' => $business->id,
                'invited_by' => $actor->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => BusinessInvitation::hashToken($token),
                'status' => BusinessInvitation::STATUS_PENDING,
                'active_key' => BusinessInvitation::activeKeyFor((int) $business->id, $email),
                'expires_at' => now()->addDays(BusinessInvitation::TTL_DAYS),
            ]);

            $this->audit->record(
                $business,
                $actor,
                MembershipAuditLog::ACTION_INVITATION_CREATED,
                null,
                $email,
                ['role' => $role, 'invitation_id' => $invitation->id],
            );

            return [$invitation, $token];
        });

        [$invitation, $token] = $result;

        // Sent after commit; the plaintext token never touches the database.
        Mail::to($invitation->email)->send(new BusinessInvitationMail($invitation, $token));

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * Rotate the token and extend the expiry of a pending invitation.
     *
     * @return array{invitation: BusinessInvitation, token: string}
     *
     * @throws ValidationException
     */
    public function resend(User $actor, BusinessInvitation $invitation): array
    {
        /** @var array{0: BusinessInvitation, 1: string} $result */
        $result = DB::transaction(function () use ($actor, $invitation): array {
            $locked = BusinessInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->effectiveStatus() !== BusinessInvitation::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'invitation' => 'Hanya undangan yang masih menunggu yang dapat dikirim ulang.',
                ]);
            }

            $business = $locked->business;
            if ($business === null) {
                throw ValidationException::withMessages([
                    'invitation' => 'Bisnis undangan tidak ditemukan.',
                ]);
            }

            $token = BusinessInvitation::generateToken();

            $locked->forceFill([
                'token_hash' => BusinessInvitation::hashToken($token),
                'expires_at' => now()->addDays(BusinessInvitation::TTL_DAYS),
                'active_key' => BusinessInvitation::activeKeyFor((int) $locked->business_id, (string) $locked->email),
            ])->save();

            $this->audit->record(
                $business,
                $actor,
                MembershipAuditLog::ACTION_INVITATION_RESENT,
                null,
                (string) $locked->email,
                ['role' => $locked->role, 'invitation_id' => $locked->id],
            );

            return [$locked, $token];
        });

        [$invitation, $token] = $result;

        Mail::to($invitation->email)->send(new BusinessInvitationMail($invitation, $token));

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * Revoke a pending invitation.
     *
     * @throws ValidationException
     */
    public function revoke(User $actor, BusinessInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $locked = BusinessInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Undangan ini tidak dapat dibatalkan.',
                ]);
            }

            $business = $locked->business;
            if ($business === null) {
                throw ValidationException::withMessages([
                    'invitation' => 'Bisnis undangan tidak ditemukan.',
                ]);
            }

            $locked->markRevoked();

            $this->audit->record(
                $business,
                $actor,
                MembershipAuditLog::ACTION_INVITATION_REVOKED,
                null,
                (string) $locked->email,
                ['role' => $locked->role, 'invitation_id' => $locked->id],
            );
        });
    }

    /**
     * Accept an invitation as the authenticated, verified user.
     *
     * Runs inside a transaction with a row lock so the same invitation cannot be
     * accepted twice and duplicate membership rows are impossible.
     *
     * @throws ValidationException
     */
    public function accept(User $user, BusinessInvitation $invitation): Business
    {
        /** @var Business $business */
        $business = DB::transaction(function () use ($user, $invitation): Business {
            $locked = BusinessInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Undangan ini sudah tidak berlaku.',
                ]);
            }

            if (! hash_equals(Str::lower((string) $locked->email), Str::lower((string) $user->email))) {
                throw ValidationException::withMessages([
                    'invitation' => 'Undangan ini ditujukan untuk alamat email yang berbeda.',
                ]);
            }

            $business = $locked->business;
            if ($business === null) {
                throw ValidationException::withMessages([
                    'invitation' => 'Bisnis undangan tidak ditemukan.',
                ]);
            }

            $existingRole = DB::table('business_user')
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->value('role');

            if ($existingRole === null) {
                $business->users()->attach($user->id, ['role' => $locked->role]);
            }

            $locked->markAccepted();

            $this->audit->record(
                $business,
                $user,
                MembershipAuditLog::ACTION_INVITATION_ACCEPTED,
                $user,
                (string) $locked->email,
                [
                    'role' => $locked->role,
                    'invitation_id' => $locked->id,
                    'was_verified' => $user->email_verified_at !== null,
                ],
            );

            return $business;
        });

        return $business;
    }

    /**
     * Persist `expired` for a business/email pair whose pending rows are past
     * their expiry, so a fresh invitation can be issued.
     */
    private function expireStaleInvitations(Business $business, string $email): void
    {
        BusinessInvitation::query()
            ->forBusiness((int) $business->id)
            ->where('email', $email)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get()
            ->each(fn (BusinessInvitation $invitation) => $invitation->markExpired());
    }

    private function emailAlreadyMember(Business $business, string $email): bool
    {
        return DB::table('business_user')
            ->join('users', 'users.id', '=', 'business_user.user_id')
            ->where('business_user.business_id', $business->id)
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    private function assertRoleAllowed(string $role): void
    {
        if ($role !== BusinessInvitation::ROLE_MEMBER) {
            throw ValidationException::withMessages([
                'role' => 'Peran undangan yang didukung saat ini hanya Anggota.',
            ]);
        }
    }
}
