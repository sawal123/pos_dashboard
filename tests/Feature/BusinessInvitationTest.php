<?php

namespace Tests\Feature;

use App\Http\Controllers\Invitations\InvitationAcceptanceController;
use App\Mail\BusinessInvitationMail;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BusinessInvitationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Creation & authorization
    // ============================================================

    public function test_guest_cannot_create_invitation(): void
    {
        $this->post(route('users.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
            ->assertRedirect(route('login'));
    }

    public function test_unverified_owner_is_redirected_to_verification_notice(): void
    {
        $owner = User::factory()->unverified()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_non_owner_member_cannot_create_invitation(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
            ->assertForbidden();

        $this->assertSame(0, BusinessInvitation::count());
    }

    public function test_owner_without_active_business_cannot_create_invitation(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($owner)
            ->post(route('users.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
            ->assertForbidden();
    }

    // ============================================================
    // Secure token
    // ============================================================

    public function test_owner_creates_invitation_with_hashed_single_use_token_and_mail_after_commit(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), [
                'email' => 'New.Member@Example.com',
                'role' => 'member',
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status');

        $invitation = BusinessInvitation::firstOrFail();

        $this->assertSame($business->id, $invitation->business_id);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertSame('new.member@example.com', $invitation->email);
        $this->assertSame('member', $invitation->role);
        $this->assertSame('pending', $invitation->status);
        $this->assertNull($invitation->accepted_at);
        $this->assertNotNull($invitation->active_key);
        $this->assertSame(64, strlen((string) $invitation->token_hash));
        $this->assertTrue($invitation->expires_at->isFuture());

        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'actor_id' => $owner->id,
            'action' => 'invitation_created',
        ]);

        Mail::assertSent(BusinessInvitationMail::class, function (BusinessInvitationMail $mail) use ($invitation): bool {
            $this->assertSame(BusinessInvitation::hashToken($mail->plainToken), $invitation->token_hash);
            $this->assertNotSame($mail->plainToken, $invitation->token_hash);

            return $mail->hasTo($invitation->email);
        });
    }

    public function test_plaintext_token_is_never_persisted_or_audited(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), ['email' => 'secret@example.com', 'role' => 'member']);

        $plainToken = null;
        Mail::assertSent(BusinessInvitationMail::class, function (BusinessInvitationMail $mail) use (&$plainToken): bool {
            $plainToken = $mail->plainToken;

            return true;
        });

        $this->assertNotNull($plainToken);

        $invitationRow = (array) DB::table('business_invitations')->first();
        $auditRows = DB::table('membership_audit_logs')->get()->toJson();

        foreach ($invitationRow as $column => $value) {
            if ($column === 'token_hash') {
                continue;
            }
            $this->assertStringNotContainsString($plainToken, (string) $value, "Token leaked in column {$column}.");
        }

        $this->assertStringNotContainsString($plainToken, $auditRows);
        $this->assertSame(BusinessInvitation::hashToken($plainToken), $invitationRow['token_hash']);
    }

    // ============================================================
    // Validation rules
    // ============================================================

    public function test_duplicate_pending_invitation_is_rejected(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $session = ['dashboard.current_business_id' => $business->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('users.invitations.store'), ['email' => 'dup@example.com', 'role' => 'member'])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)->withSession($session)
            ->post(route('users.invitations.store'), ['email' => 'dup@example.com', 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, BusinessInvitation::where('email', 'dup@example.com')->count());
    }

    public function test_cannot_invite_an_email_that_is_already_a_member(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->attachMember($business, 'member', ['email' => 'member@example.com']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), ['email' => 'member@example.com', 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, BusinessInvitation::count());
    }

    public function test_only_member_role_can_be_invited(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        foreach (['owner', 'cashier'] as $role) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->post(route('users.invitations.store'), ['email' => 'role@example.com', 'role' => $role])
                ->assertSessionHasErrors('role');
        }

        $this->assertSame(0, BusinessInvitation::count());
    }

    // ============================================================
    // Resend & revoke
    // ============================================================

    public function test_resend_rotates_token_and_extends_expiry(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [$invitation] = $this->makePendingInvitation($business, $owner, 'resend@example.com', now()->addDay());
        $originalHash = $invitation->token_hash;

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.resend', $invitation->id));

        $response->assertRedirect(route('users.index'));

        $fresh = $invitation->fresh();
        $this->assertNotSame($originalHash, $fresh->token_hash);
        $this->assertSame('pending', $fresh->status);
        $this->assertTrue($fresh->expires_at->greaterThan(now()->addDays(6)));
        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'action' => 'invitation_resent',
        ]);
        Mail::assertSent(BusinessInvitationMail::class);
    }

    public function test_owner_can_revoke_pending_invitation(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [$invitation] = $this->makePendingInvitation($business, $owner, 'revoke@example.com');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.revoke', $invitation->id))
            ->assertRedirect(route('users.index'));

        $fresh = $invitation->fresh();
        $this->assertSame('revoked', $fresh->status);
        $this->assertNotNull($fresh->revoked_at);
        $this->assertNull($fresh->active_key);
        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'action' => 'invitation_revoked',
        ]);
    }

    public function test_non_owner_cannot_resend_or_revoke(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [$invitation] = $this->makePendingInvitation($business, $owner, 'member-action@example.com');
        $member = $this->attachMember($business, 'member');

        $this->actingAs($member)->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.resend', $invitation->id))->assertForbidden();
        $this->actingAs($member)->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.revoke', $invitation->id))->assertForbidden();
    }

    public function test_cross_tenant_invitation_actions_return_404(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness();
        [$ownerB, $businessB] = $this->makeOwnerWithBusiness();
        [$invitationB] = $this->makePendingInvitation($businessB, $ownerB, 'foreign@example.com');

        $this->actingAs($ownerA)->withSession(['dashboard.current_business_id' => $businessA->id])
            ->post(route('users.invitations.resend', $invitationB->id))->assertNotFound();
        $this->actingAs($ownerA)->withSession(['dashboard.current_business_id' => $businessA->id])
            ->post(route('users.invitations.revoke', $invitationB->id))->assertNotFound();
    }

    public function test_revoking_allows_a_fresh_invitation_for_the_same_email(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [$invitation] = $this->makePendingInvitation($business, $owner, 'again@example.com');
        $invitation->markRevoked();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), ['email' => 'again@example.com', 'role' => 'member'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, BusinessInvitation::where('email', 'again@example.com')
            ->where('status', 'pending')->count());
    }

    // ============================================================
    // Acceptance
    // ============================================================

    public function test_unknown_token_returns_404(): void
    {
        $this->get(route('invitations.show', ['token' => 'doesnotexist']))->assertNotFound();
    }

    public function test_guest_invitation_page_prompts_login_and_remembers_token(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [, $token] = $this->makePendingInvitation($business, $owner, 'guest@example.com');

        $response = $this->get(route('invitations.show', ['token' => $token]));

        $response->assertOk();
        $response->assertSee($business->name);
        $response->assertSee('Masuk');
        $response->assertSessionHas(InvitationAcceptanceController::SESSION_KEY, $token);
    }

    public function test_acceptance_requires_login(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [, $token] = $this->makePendingInvitation($business, $owner, 'login@example.com');

        $this->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('login'));
    }

    public function test_acceptance_requires_verified_email(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [, $token] = $this->makePendingInvitation($business, $owner, 'unverified@example.com');
        $invitee = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_wrong_email_cannot_accept_the_invitation(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [, $token] = $this->makePendingInvitation($business, $owner, 'intended@example.com');
        $intruder = User::factory()->create(['email' => 'intruder@example.com', 'email_verified_at' => now()]);

        $this->actingAs($intruder)->get(route('invitations.show', ['token' => $token]))->assertForbidden();
        $this->actingAs($intruder)->post(route('invitations.accept', ['token' => $token]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $intruder->id,
        ]);
    }

    public function test_existing_user_accepts_and_becomes_member_not_owner(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $invitee = User::factory()->create(['email' => 'existing@example.com', 'email_verified_at' => now()]);
        [, $token] = $this->makePendingInvitation($business, $owner, 'existing@example.com');

        $this->actingAs($invitee)->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Terima Undangan');

        $response = $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
        $this->assertFalse($business->fresh()->isOwnedBy($invitee));
        $this->assertSame($business->id, session(DashboardBusinessContext::SESSION_KEY));

        $fresh = BusinessInvitation::where('email', 'existing@example.com')->firstOrFail();
        $this->assertSame('accepted', $fresh->status);
        $this->assertNotNull($fresh->accepted_at);
        $this->assertNull($fresh->active_key);

        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'actor_id' => $invitee->id,
            'target_user_id' => $invitee->id,
            'action' => 'invitation_accepted',
        ]);
    }

    public function test_invitation_cannot_be_accepted_twice(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $invitee = User::factory()->create(['email' => 'twice@example.com', 'email_verified_at' => now()]);
        [, $token] = $this->makePendingInvitation($business, $owner, 'twice@example.com');

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertSessionHasErrors('invitation');

        $this->assertSame(1, DB::table('business_user')
            ->where('business_id', $business->id)
            ->where('user_id', $invitee->id)
            ->count());
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $invitee = User::factory()->create(['email' => 'expired@example.com', 'email_verified_at' => now()]);
        [, $token] = $this->makePendingInvitation($business, $owner, 'expired@example.com', now()->subMinute());

        $this->actingAs($invitee)->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('tidak berlaku');

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $invitee = User::factory()->create(['email' => 'revoked@example.com', 'email_verified_at' => now()]);
        [$invitation, $token] = $this->makePendingInvitation($business, $owner, 'revoked@example.com');
        $invitation->markRevoked();

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_new_user_registers_through_fortify_then_accepts(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        [, $token] = $this->makePendingInvitation($business, $owner, 'newbie@example.com');

        $this->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Daftar Akun Baru');

        $this->post(route('register.store'), [
            'name' => 'Newbie',
            'email' => 'newbie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $newUser = User::where('email', 'newbie@example.com')->firstOrFail();
        // Email verification itself is covered by the Fortify suite; simulate it here.
        $newUser->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($newUser)->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);
    }

    public function test_accepting_does_not_change_credentials_or_other_memberships(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $invitee = User::factory()->create(['email' => 'multi@example.com', 'email_verified_at' => now()]);
        $otherBusiness = Business::factory()->create();
        $invitee->businesses()->attach($otherBusiness->id, ['role' => 'member']);
        $passwordBefore = $invitee->password;

        [, $token] = $this->makePendingInvitation($business, $owner, 'multi@example.com');

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard'));

        $fresh = $invitee->fresh();
        $this->assertSame($passwordBefore, $fresh->password);
        $this->assertSame('multi@example.com', $fresh->email);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $otherBusiness->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertFalse($business->fresh()->isOwnedBy($fresh));
    }

    // ============================================================
    // Throttling
    // ============================================================

    public function test_invitation_creation_is_throttled(): void
    {
        Mail::fake();
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $session = ['dashboard.current_business_id' => $business->id];

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($owner)->withSession($session)
                ->post(route('users.invitations.store'), [
                    'email' => "throttle{$i}@example.com",
                    'role' => 'member',
                ])
                ->assertRedirect(route('users.index'));
        }

        $this->actingAs($owner)->withSession($session)
            ->post(route('users.invitations.store'), ['email' => 'throttle-11@example.com', 'role' => 'member'])
            ->assertStatus(429);

        $this->assertSame(10, BusinessInvitation::count());
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$owner, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attachMember(Business $business, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $business->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    /**
     * @return array{0: BusinessInvitation, 1: string}
     */
    private function makePendingInvitation(
        Business $business,
        ?User $inviter,
        string $email,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $email = strtolower($email);
        $token = BusinessInvitation::generateToken();

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'invited_by' => $inviter?->id,
            'email' => $email,
            'role' => BusinessInvitation::ROLE_MEMBER,
            'token_hash' => BusinessInvitation::hashToken($token),
            'status' => BusinessInvitation::STATUS_PENDING,
            'active_key' => BusinessInvitation::activeKeyFor((int) $business->id, $email),
            'expires_at' => $expiresAt ?? now()->addDays(BusinessInvitation::TTL_DAYS),
        ]);

        return [$invitation, $token];
    }
}
