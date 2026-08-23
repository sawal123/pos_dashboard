<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileSyncContextTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Create a verified user with a mobile token. */
    private function userWithMobileToken(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return [$user, $token];
    }

    // =========================================================================
    // GET /api/mobile/context
    // =========================================================================

    public function test_authenticated_mobile_token_can_read_context(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'businesses' => [
                    '*' => ['id', 'name', 'subscription', 'cloud_access', 'outlets'],
                ],
            ],
        ]);
    }

    public function test_unauthenticated_request_to_context_returns_401(): void
    {
        $response = $this->getJson('/api/mobile/context');

        $response->assertStatus(401);
    }

    public function test_token_without_mobile_ability_is_rejected_from_context(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('web-api', ['web'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Mobile API token is required.',
            'code' => 'MOBILE_TOKEN_REQUIRED',
        ]);
    }

    public function test_context_only_shows_businesses_belonging_to_authenticated_user(): void
    {
        [$userA, $tokenA] = $this->userWithMobileToken();
        [$userB] = $this->userWithMobileToken();

        $businessA = Business::factory()->create(['name' => 'Business A']);
        $businessB = Business::factory()->create(['name' => 'Business B']);

        $userA->businesses()->attach($businessA, ['role' => 'owner']);
        $userB->businesses()->attach($businessB, ['role' => 'owner']);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/mobile/context');

        $response->assertStatus(200);

        $ids = collect($response->json('data.businesses'))->pluck('id')->toArray();
        $this->assertContains($businessA->id, $ids);
        $this->assertNotContains($businessB->id, $ids);
    }

    public function test_cloud_subscription_results_in_cloud_access_true(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertStatus(200);

        $businesses = $response->json('data.businesses');
        $this->assertCount(1, $businesses);
        $this->assertTrue($businesses[0]['cloud_access']);
    }

    public function test_free_subscription_results_in_cloud_access_false(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertStatus(200);

        $businesses = $response->json('data.businesses');
        $this->assertCount(1, $businesses);
        $this->assertFalse($businesses[0]['cloud_access']);
    }

    public function test_expired_subscription_results_in_cloud_access_false(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertStatus(200);

        $businesses = $response->json('data.businesses');
        $this->assertFalse($businesses[0]['cloud_access']);
    }

    // =========================================================================
    // POST /api/mobile/devices
    // =========================================================================

    public function test_register_device_success(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'POS-TEST-01',
                'name' => 'Test POS',
                'platform' => 'android',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'identifier', 'business_id', 'outlet_id', 'status', 'name', 'platform'],
        ]);
        $response->assertJson([
            'data' => [
                'identifier' => 'POS-TEST-01',
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'status' => 'active',
            ],
        ]);

        $this->assertDatabaseHas('devices', [
            'identifier' => 'POS-TEST-01',
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);
    }

    public function test_register_same_identifier_is_idempotent_and_updates_last_seen_at(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create the device once
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'IDEM-01',
            'status' => 'active',
            'registered_at' => now()->subDay(),
            'last_seen_at' => null,
        ]);

        // Re-register the same identifier
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'IDEM-01',
                'name' => 'POS 1',
                'platform' => null,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['id' => $device->id]]);

        // No duplicate created
        $this->assertDatabaseCount('devices', 1);

        // last_seen_at updated
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_register_device_for_business_user_is_not_member_of_returns_403(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $otherBusiness = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $otherBusiness->id]);
        $outlet = Outlet::factory()->create(['business_id' => $otherBusiness->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $otherBusiness->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'CROSS-01',
                'name' => 'Cross POS',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'BUSINESS_ACCESS_DENIED']);
    }

    public function test_register_device_with_outlet_from_different_business_returns_403(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $myBusiness = Business::factory()->create();
        $user->businesses()->attach($myBusiness, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $myBusiness->id]);

        $otherBusiness = Business::factory()->create();
        $alienOutlet = Outlet::factory()->create(['business_id' => $otherBusiness->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $myBusiness->id,
                'outlet_id' => $alienOutlet->id,  // belongs to another business
                'device_identifier' => 'CROSS-OUTLET-01',
                'name' => 'Cross Outlet POS',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'OUTLET_ACCESS_DENIED']);
    }

    public function test_register_device_with_free_subscription_returns_403(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'FREE-01',
                'name' => 'Free POS',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_resolving_inactive_device_returns_403_device_inactive(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Inactive POS',
            'identifier' => 'INACTIVE-01',
            'status' => 'inactive',
            'registered_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'INACTIVE-01',
                'name' => 'Inactive POS',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Device is inactive.',
            'code' => 'DEVICE_INACTIVE',
        ]);
    }

    public function test_register_device_without_mobile_token_returns_403(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('web-api', ['web'])->plainTextToken;

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'WEB-01',
                'name' => 'Web Token Device',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'MOBILE_TOKEN_REQUIRED']);
    }

    public function test_unauthenticated_register_device_returns_401(): void
    {
        $response = $this->postJson('/api/mobile/devices', [
            'business_id' => 1,
            'outlet_id' => 1,
            'device_identifier' => 'TEST-01',
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_same_identifier_different_outlet_returns_409_device_outlet_mismatch(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet1 = Outlet::factory()->create(['business_id' => $business->id]);
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id]);

        // Register device on outlet1
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'name' => 'POS Outlet1',
            'identifier' => 'MISMATCH-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // Re-register with outlet2 — must be rejected
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet2->id,
                'device_identifier' => 'MISMATCH-01',
                'name' => 'POS Outlet1',
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Device is registered to a different outlet.',
            'code' => 'DEVICE_OUTLET_MISMATCH',
        ]);

        // Outlet unchanged
        $this->assertDatabaseHas('devices', [
            'identifier' => 'MISMATCH-01',
            'outlet_id' => $outlet1->id,
        ]);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_simulated_unique_race_resolves_existing_device_without_500(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Simulate a concurrent request winning the INSERT race via a SQLite BEFORE INSERT
        // trigger. RAISE(FAIL) preserves the trigger's own INSERT (the "winner" row) while
        // aborting the outer INSERT with a UNIQUE constraint error.  Laravel maps this to
        // UniqueConstraintViolationException, which the controller's catch block must handle.
        DB::unprepared(sprintf("
            CREATE TRIGGER simulate_race_insert
            BEFORE INSERT ON \"devices\"
            WHEN NEW.\"identifier\" = 'RACE-01'
            BEGIN
                INSERT INTO \"devices\"
                    (\"business_id\", \"outlet_id\", \"name\", \"identifier\", \"platform\",
                     \"status\", \"notes\", \"registered_at\", \"last_seen_at\",
                     \"created_at\", \"updated_at\")
                VALUES
                    (NEW.\"business_id\", NEW.\"outlet_id\", 'Race Winner', NEW.\"identifier\",
                     NULL, 'active', NULL,
                     NEW.\"registered_at\", NULL, NEW.\"created_at\", NEW.\"updated_at\");
                SELECT RAISE(FAIL, 'UNIQUE constraint failed: devices.business_id, devices.identifier');
            END
        "));

        try {
            // Flow: SELECT → null → INSERT → trigger inserts winner → RAISE(FAIL) →
            //        UniqueConstraintViolationException caught → re-fetch winner → 200
            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/mobile/devices', [
                    'business_id' => $business->id,
                    'outlet_id' => $outlet->id,
                    'device_identifier' => 'RACE-01',
                    'name' => 'Race POS',
                ]);

            $response->assertStatus(200);

            // Exactly one device row — the winner inserted by the trigger
            $this->assertDatabaseCount('devices', 1);
            $this->assertDatabaseHas('devices', [
                'identifier' => 'RACE-01',
                'business_id' => $business->id,
            ]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS "simulate_race_insert"');
        }
    }

    public function test_duplicate_registration_does_not_create_second_device(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // First registration
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'DUP-01',
                'name' => 'Dup POS',
            ])->assertStatus(200);

        // Second registration — same payload
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'DUP-01',
                'name' => 'Dup POS',
            ])->assertStatus(200);

        $this->assertDatabaseCount('devices', 1);
    }

    // =========================================================================
    // P11 sync endpoints remain PASS (regression guard)
    // =========================================================================

    public function test_p11_sync_push_endpoint_still_passes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'P11 POS',
            'identifier' => 'P11-POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'P11-POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(200);
    }

    public function test_p11_sync_pull_endpoint_still_passes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'P11 POS',
            'identifier' => 'P11-PULL-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/sync/pull?'.http_build_query([
                'business_id' => $business->id,
                'device_identifier' => 'P11-PULL-01',
                'after' => 0,
                'limit' => 10,
            ]));

        $response->assertStatus(200);
    }
}
