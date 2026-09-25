<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DASH-10B2 — mobile/API role authorization.
 *
 * Owner and member keep the existing sync contract. Cashier (and any unknown
 * role) is explicitly denied the mobile sync push/pull and device-registration
 * endpoints, because the sync payload accepts every entity/operation type and
 * is not cashier-safe yet. See docs/dashboard/DASH10B2_CASHIER_RBAC.md.
 */
class MobileRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_push(): void
    {
        [$user, $business, $device] = $this->makeSyncBusiness('cashier');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
            ->assertStatus(403)
            ->assertJson([
                'message' => 'This role is not supported by the mobile sync API yet.',
                'code' => 'MOBILE_ROLE_NOT_SUPPORTED',
            ]);
    }

    public function test_cashier_cannot_pull(): void
    {
        [$user, $business, $device] = $this->makeSyncBusiness('cashier');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier='.$device)
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    public function test_cashier_cannot_register_a_device(): void
    {
        [$user, $business, $device] = $this->makeSyncBusiness('cashier');
        $outlet = Outlet::where('business_id', $business->id)->firstOrFail();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'POS-NEW-CASHIER',
                'name' => 'Kasir Baru',
                'platform' => 'android',
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);

        $this->assertDatabaseMissing('devices', ['identifier' => 'POS-NEW-CASHIER']);
    }

    public function test_unknown_role_cannot_push(): void
    {
        [$user, $business, $device] = $this->makeSyncBusiness('supervisor');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    public function test_owner_and_member_keep_the_sync_contract(): void
    {
        foreach (['owner', 'member'] as $role) {
            // Sanctum caches the resolved guard user; clear it so the loop's
            // second actor is authenticated by their own bearer token.
            Auth::forgetGuards();

            [$user, $business, $device] = $this->makeSyncBusiness($role);
            $token = $this->tokenFor($user);

            $this->withToken($token)
                ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
                ->assertOk()
                ->assertJsonPath('data.duplicate', false);

            $this->withToken($token)
                ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier='.$device)
                ->assertOk();

            // Members can still register devices.
            $outlet = Outlet::where('business_id', $business->id)->firstOrFail();
            $this->withToken($token)
                ->postJson('/api/mobile/devices', [
                    'business_id' => $business->id,
                    'outlet_id' => $outlet->id,
                    'device_identifier' => 'POS-'.$role.'-EXTRA',
                    'name' => 'Perangkat '.$role,
                    'platform' => 'android',
                ])
                ->assertOk();
        }
    }

    public function test_role_change_applies_to_an_existing_sanctum_token(): void
    {
        [$user, $business, $device] = $this->makeSyncBusiness('member');
        $token = $this->tokenFor($user);

        // Member token works.
        $this->withToken($token)
            ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
            ->assertOk();

        // Promote the same membership to cashier.
        $this->setRole($business, $user, 'cashier');

        // The *same* token is now denied: the role lives on the membership row,
        // never on the token.
        $this->withToken($token)
            ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);

        // Demoting back restores access for the same token.
        $this->setRole($business, $user, 'member');

        $this->withToken($token)
            ->postJson('/api/sync/push', $this->pushPayload($business->id, $device))
            ->assertOk();
    }

    public function test_role_is_denied_before_subscription_is_evaluated(): void
    {
        // Cashier without any subscription still gets the role code, proving the
        // role gate is evaluated independently of billing state.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'cashier']);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/sync/push', $this->pushPayload($business->id, 'POS-NONE'))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    public function test_mobile_context_remains_available_to_cashier(): void
    {
        // Documented residual boundary: the read-only bootstrap context is still
        // reachable; only sync push/pull and device registration are blocked.
        [$user] = $this->makeSyncBusiness('cashier');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/context')
            ->assertOk();
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business, 2: string} [user, business, device identifier]
     */
    private function makeSyncBusiness(string $role): array
    {
        $identifier = 'POS-'.strtoupper($role);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS '.$role,
            'identifier' => $identifier,
            'status' => 'active',
            'registered_at' => now(),
        ]);

        return [$user, $business, $identifier];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('mobile-api', ['mobile'])->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function pushPayload(int $businessId, string $deviceIdentifier): array
    {
        return [
            'business_id' => $businessId,
            'device_identifier' => $deviceIdentifier,
            'request_id' => (string) Str::uuid(),
            'changes' => [],
        ];
    }

    private function setRole(Business $business, User $user, string $role): void
    {
        DB::table('business_user')
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->update(['role' => $role, 'updated_at' => now()]);
    }
}
