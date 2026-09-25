<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Authorization
    // ============================================================

    public function test_guest_cannot_remove_a_member(): void
    {
        $member = User::factory()->create();

        $this->delete(route('users.members.destroy', $member->id))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness(unverifiedOwner: true);
        $member = $this->attachMember($business, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $member->id))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_non_owner_member_cannot_remove_a_member(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');
        $other = $this->attachMember($business, 'member', ['email' => 'other@example.com']);

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $other->id))
            ->assertForbidden();

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $other->id,
        ]);
    }

    // ============================================================
    // Removal
    // ============================================================

    public function test_owner_removes_a_member_of_the_active_business(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');
        $member->createToken('mobile-api', ['mobile']);
        $tokensBefore = $member->tokens()->count();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $member->id));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $member->id,
        ]);

        // The global account survives and its tokens are NOT revoked.
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertSame($tokensBefore, $member->tokens()->count());

        $this->assertDatabaseHas('membership_audit_logs', [
            'business_id' => $business->id,
            'actor_id' => $owner->id,
            'target_user_id' => $member->id,
            'action' => 'membership_removed',
        ]);
    }

    public function test_owner_cannot_be_removed_and_last_owner_is_kept(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $owner->id))
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertSame(1, $business->owners()->count());
    }

    public function test_cannot_remove_a_user_from_another_business(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $foreignBusiness = Business::factory()->create();
        $foreignMember = $this->attachMember($foreignBusiness, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $foreignMember->id))
            ->assertNotFound();

        $this->assertDatabaseHas('business_user', [
            'business_id' => $foreignBusiness->id,
            'user_id' => $foreignMember->id,
        ]);
    }

    public function test_cannot_remove_a_non_member(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $outsider->id))
            ->assertNotFound();
    }

    public function test_membership_on_other_businesses_remains_intact(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $shared = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($shared->id, ['role' => 'member']);
        $otherBusiness = Business::factory()->create();
        $otherBusiness->users()->attach($shared->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $shared->id))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $shared->id,
        ]);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $otherBusiness->id,
            'user_id' => $shared->id,
            'role' => 'member',
        ]);
    }

    // ============================================================
    // Access is denied after removal (dashboard + sync)
    // ============================================================

    public function test_removed_member_cannot_switch_back_to_that_business(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $member = $this->attachMember($business, 'member');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('users.members.destroy', $member->id))
            ->assertRedirect(route('users.index'));

        $this->actingAs($member)
            ->post(route('dashboard.business-context.update'), ['business_id' => $business->id])
            ->assertForbidden();
    }

    public function test_sync_is_denied_for_the_removed_business_but_works_for_another(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        [$businessA, $ownerA] = $this->makeSyncReadyBusiness($member, 'BUS-A');
        [$businessB] = $this->makeSyncReadyBusiness($member, 'BUS-B');

        $this->actingAs($ownerA)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->delete(route('users.members.destroy', $member->id))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $businessA->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $businessB->id,
            'user_id' => $member->id,
        ]);

        // Authenticate the API calls as the removed member. The dashboard
        // request above acted as the owner, so its guard must be cleared first
        // (Sanctum's stateful guard would otherwise win over the member).
        Auth::forgetGuards();
        Sanctum::actingAs($member, ['mobile']);

        $denied = $this->postJson('/api/sync/push', [
            'business_id' => $businessA->id,
            'device_identifier' => 'POS-BUS-A',
            'request_id' => (string) Str::uuid(),
            'changes' => [],
        ]);

        $denied->assertStatus(403);
        $denied->assertJson([
            'message' => 'Business access denied.',
            'code' => 'BUSINESS_ACCESS_DENIED',
        ]);

        // The untouched business still works: the removal did not revoke every
        // token/ability, only the membership of the removed business.
        $allowed = $this->postJson('/api/sync/push', [
            'business_id' => $businessB->id,
            'device_identifier' => 'POS-BUS-B',
            'request_id' => (string) Str::uuid(),
            'changes' => [],
        ]);

        $allowed->assertStatus(200);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(bool $unverifiedOwner = false): array
    {
        $owner = User::factory()->create([
            'email_verified_at' => $unverifiedOwner ? null : now(),
        ]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);

        if (! $unverifiedOwner) {
            $this->withSession(['dashboard.current_business_id' => $business->id]);
        }

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
     * A business owned by a fresh owner where the given user is a plain
     * `member`, with an active cloud subscription and one active device,
     * matching the P38/P37 sync prerequisites.
     *
     * @return array{0: Business, 1: User}
     */
    private function makeSyncReadyBusiness(User $member, string $label): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        $business->users()->attach($member->id, ['role' => 'member']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS '.$label,
            'identifier' => 'POS-'.$label,
            'status' => 'active',
            'registered_at' => now(),
        ]);

        return [$business, $owner];
    }
}
