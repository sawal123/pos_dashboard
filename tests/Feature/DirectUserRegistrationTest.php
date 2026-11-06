<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\MembershipAuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DirectUserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_directly_register_cashier_with_password(): void
    {
        Mail::fake();
        [$owner, $business] = $this->setupBusinessWithOwner();

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Budi Kasir',
                'email' => 'budi.kasir@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status', 'Pengguna Budi Kasir (budi.kasir@example.com) berhasil didaftarkan langsung sebagai Kasir.');

        // User is created in database
        $user = User::where('email', 'budi.kasir@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Budi Kasir', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret12345', $user->password));

        // Attached to business as cashier
        $this->assertTrue($business->users()->where('users.id', $user->id)->exists());
        $this->assertSame(Business::ROLE_CASHIER, $business->users()->where('users.id', $user->id)->first()->pivot->role);

        // Can log in with password
        $this->post(route('login'), [
            'email' => 'budi.kasir@example.com',
            'password' => 'secret12345',
        ])->assertRedirect();

        // Audit log recorded
        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'actor_id' => $owner->id,
            'target_user_id' => $user->id,
            'target_email' => 'budi.kasir@example.com',
            'action' => MembershipAuditLog::ACTION_MEMBER_DIRECTLY_REGISTERED,
        ]);

        // No invitation mail was sent since it was registered directly
        Mail::assertNothingQueued();
    }

    public function test_owner_can_directly_register_member_with_password(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Siti Manajer',
                'email' => 'siti.manajer@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => Business::ROLE_MEMBER,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status', 'Pengguna Siti Manajer (siti.manajer@example.com) berhasil didaftarkan langsung sebagai Anggota.');

        $user = User::where('email', 'siti.manajer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(Business::ROLE_MEMBER, $business->users()->where('users.id', $user->id)->first()->pivot->role);
    }

    public function test_direct_registration_validates_required_fields(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();

        // Missing name, password
        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'email' => 'test@example.com',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertSessionHasErrors(['name', 'password']);
    }

    public function test_direct_registration_validates_password_confirmation(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'different-password',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_direct_registration_rejects_already_member(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();
        $existing = User::factory()->create(['email' => 'existing@example.com', 'email_verified_at' => now()]);
        $business->users()->attach($existing->id, ['role' => Business::ROLE_MEMBER]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Duplicate Member',
                'email' => 'existing@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_direct_registration_rejects_existing_platform_user_with_invitation_advice(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();
        // User exists in platform, but not in this business
        User::factory()->create(['email' => 'otherplatformuser@example.com']);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Foreign User',
                'email' => 'otherplatformuser@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_direct_registration_marks_pending_invitation_accepted(): void
    {
        [$owner, $business] = $this->setupBusinessWithOwner();

        // Suppose an invitation was previously sent to this email
        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'invited_by' => $owner->id,
            'email' => 'invited@example.com',
            'role' => Business::ROLE_CASHIER,
            'token_hash' => BusinessInvitation::hashToken(BusinessInvitation::generateToken()),
            'status' => BusinessInvitation::STATUS_PENDING,
            'active_key' => BusinessInvitation::activeKeyFor((int) $business->id, 'invited@example.com'),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Direct Invitee',
                'email' => 'invited@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertRedirect(route('users.index'));

        $invitation->refresh();
        $this->assertSame(BusinessInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNull($invitation->active_key);
    }

    public function test_cashier_cannot_directly_register_users(): void
    {
        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($cashier->id, ['role' => Business::ROLE_CASHIER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $response = $this->actingAs($cashier)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Sub Kasir',
                'email' => 'subkasir@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertForbidden();
    }

    public function test_mode_invite_still_queues_invitation_mail(): void
    {
        Mail::fake();
        [$owner, $business] = $this->setupBusinessWithOwner();

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'invite',
                'email' => 'invitee@example.com',
                'role' => Business::ROLE_CASHIER,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status');

        Mail::assertQueued(\App\Mail\BusinessInvitationMail::class);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function setupBusinessWithOwner(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return [$owner, $business];
    }
}
