<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BusinessMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_attached_to_business_with_owner_role(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertTrue($user->businesses->contains($business));
    }

    public function test_business_can_have_users_with_pivot_data(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->assertCount(1, $business->users);
        $this->assertSame('owner', $business->users->first()?->pivot?->role);
    }

    public function test_pivot_stores_owner_role(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);

        $member = $business->users()->first();
        $this->assertNotNull($member);
        $this->assertSame('owner', $member->pivot->role);
        $this->assertTrue($user->ownsBusiness($business));
        $this->assertTrue($business->isOwnedBy($user));
    }

    public function test_duplicate_membership_is_rejected_by_database(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->expectException(QueryException::class);

        $business->users()->attach($user->id, ['role' => 'owner']);
    }

    public function test_member_user_can_view_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->assertTrue(Gate::forUser($user)->allows('view', $business));
    }

    public function test_non_member_user_cannot_view_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('view', $business));
    }

    public function test_owner_is_recognized_and_can_update_business(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($owner->id, ['role' => 'owner']);

        $this->assertTrue($owner->ownsBusiness($business));
        $this->assertTrue($business->isOwnedBy($owner));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $business));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $business));
    }

    public function test_non_owner_member_is_not_recognized_as_owner(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'member']);

        $this->assertTrue($user->belongsToBusiness($business));
        $this->assertFalse($user->ownsBusiness($business));
        $this->assertFalse($business->isOwnedBy($user));
        $this->assertTrue(Gate::forUser($user)->allows('view', $business));
        $this->assertFalse(Gate::forUser($user)->allows('update', $business));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $business));
    }

    public function test_relations_do_not_leak_other_users_businesses(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $businessA = Business::factory()->create(['name' => 'Business A']);
        $businessB = Business::factory()->create(['name' => 'Business B']);

        $businessA->users()->attach($userA->id, ['role' => 'owner']);
        $businessB->users()->attach($userB->id, ['role' => 'owner']);

        $userABusinesses = $userA->businesses;
        $this->assertCount(1, $userABusinesses);
        $this->assertTrue($userABusinesses->contains($businessA));
        $this->assertFalse($userABusinesses->contains($businessB));
    }

    public function test_deleting_business_safely_removes_pivot_memberships(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $business->delete();

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_membership_requires_explicit_role(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $this->expectException(QueryException::class);

        $business->users()->attach($user->id);
    }
}
