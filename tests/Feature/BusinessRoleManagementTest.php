<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASH-10B2 — owner-driven role management (`member` <-> `cashier`).
 */
class BusinessRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Happy path
    // ============================================================

    public function test_owner_can_change_member_to_cashier_and_back(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $target = $this->attachMember($business, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertSame('cashier', $this->pivotRole($business, $target));

        $log = MembershipAuditLog::where('action', MembershipAuditLog::ACTION_ROLE_CHANGED)->firstOrFail();
        $this->assertSame($business->id, $log->business_id);
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame($target->id, $log->target_user_id);
        $this->assertSame('member', $log->metadata['old_role']);
        $this->assertSame('cashier', $log->metadata['new_role']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $target->id), ['role' => 'member'])
            ->assertRedirect(route('users.index'));

        $this->assertSame('member', $this->pivotRole($business, $target));
        $this->assertSame(2, MembershipAuditLog::where('action', MembershipAuditLog::ACTION_ROLE_CHANGED)->count());
    }

    public function test_role_change_takes_effect_on_the_next_request(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $target = $this->attachMember($business, 'member');

        // A member may view reports.
        $this->actingAs($target)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
            ->assertRedirect(route('users.index'));

        // The very next request is evaluated against the new role.
        $this->actingAs($target)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    // ============================================================
    // Owner protection
    // ============================================================

    public function test_owner_cannot_change_their_own_role(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $owner->id), ['role' => 'member'])
            ->assertSessionHasErrors('role');

        $this->assertSame('owner', $this->pivotRole($business, $owner));
        $this->assertSame(1, $business->owners()->count());
    }

    public function test_owner_row_cannot_be_changed_even_by_another_owner(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $otherOwner = $this->attachMember($business, 'owner', ['email' => 'co-owner@example.com']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $otherOwner->id), ['role' => 'cashier'])
            ->assertSessionHasErrors('role');

        $this->assertSame('owner', $this->pivotRole($business, $otherOwner));
        $this->assertSame(2, $business->owners()->count());
    }

    // ============================================================
    // Allowlist & unknown roles
    // ============================================================

    public function test_role_outside_the_allowlist_is_rejected(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $target = $this->attachMember($business, 'member');

        foreach (['owner', 'admin', 'supervisor'] as $forbiddenRole) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->patch(route('users.members.role.update', $target->id), ['role' => $forbiddenRole])
                ->assertSessionHasErrors('role');
        }

        $this->assertSame('member', $this->pivotRole($business, $target));
        $this->assertSame(0, MembershipAuditLog::where('action', MembershipAuditLog::ACTION_ROLE_CHANGED)->count());
    }

    public function test_unmapped_current_role_cannot_be_changed(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $target = $this->attachMember($business, 'supervisor');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
            ->assertSessionHasErrors('role');

        $this->assertSame('supervisor', $this->pivotRole($business, $target));
    }

    // ============================================================
    // Authorization / tenancy
    // ============================================================

    public function test_member_and_cashier_cannot_change_roles(): void
    {
        foreach (['member', 'cashier'] as $actorRole) {
            $business = Business::factory()->create();
            $owner = $this->attachMember($business, 'owner', ['email' => $actorRole.'-owner@example.com']);
            $actor = $this->attachMember($business, $actorRole, ['email' => $actorRole.'-actor@example.com']);
            $target = $this->attachMember($business, 'member', ['email' => $actorRole.'-target@example.com']);

            $this->actingAs($actor)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
                ->assertForbidden();

            $this->assertSame('member', $this->pivotRole($business, $target));
            $this->assertSame(0, MembershipAuditLog::where('action', MembershipAuditLog::ACTION_ROLE_CHANGED)->count());
        }
    }

    public function test_cross_tenant_role_change_returns_404(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $foreignBusiness = Business::factory()->create();
        $foreignMember = $this->attachMember($foreignBusiness, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $foreignMember->id), ['role' => 'cashier'])
            ->assertNotFound();

        $this->assertSame('member', $this->pivotRole($foreignBusiness, $foreignMember));
    }

    public function test_role_change_only_affects_the_active_business(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $shared = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($shared->id, ['role' => 'member']);
        $otherBusiness = Business::factory()->create();
        $otherBusiness->users()->attach($shared->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $shared->id), ['role' => 'cashier'])
            ->assertRedirect(route('users.index'));

        $this->assertSame('cashier', $this->pivotRole($business, $shared));
        $this->assertSame('member', $this->pivotRole($otherBusiness, $shared));
    }

    public function test_guest_cannot_change_roles(): void
    {
        $user = User::factory()->create();

        $this->patch(route('users.members.role.update', $user->id), ['role' => 'cashier'])
            ->assertRedirect(route('login'));
    }

    public function test_unverified_owner_is_redirected_to_verification_notice(): void
    {
        $owner = User::factory()->unverified()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        $target = $this->attachMember($business, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
            ->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // Removal now covers cashier too (DASH-10B2)
    // ============================================================

    public function test_owner_can_remove_a_cashier(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $cashier = $this->attachMember($business, 'cashier');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $cashier->id))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $cashier->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $cashier->id]);
        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'target_user_id' => $cashier->id,
            'action' => 'membership_removed',
        ]);
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

    private function pivotRole(Business $business, User $user): ?string
    {
        $role = $business->users()->where('users.id', $user->id)->first()?->pivot?->getAttribute('role');

        return is_string($role) ? $role : null;
    }
}
