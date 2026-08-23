<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_request_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/sync/push', [
            'business_id' => 1,
            'device_identifier' => 'POS-01',
            'request_id' => (string) Str::uuid(),
            'changes' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_token_without_mobile_ability_returns_403_mobile_token_required(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('web-api', ['web'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Mobile API token is required.',
            'code' => 'MOBILE_TOKEN_REQUIRED',
        ]);
    }

    public function test_user_not_member_of_business_returns_403_business_access_denied(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Business access denied.',
            'code' => 'BUSINESS_ACCESS_DENIED',
        ]);
    }

    public function test_free_subscription_business_returns_403_cloud_subscription_required(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Cloud subscription is required.',
            'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
        ]);
    }

    public function test_expired_cloud_subscription_returns_403_cloud_subscription_required(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Cloud subscription is required.',
            'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
        ]);
    }

    public function test_business_without_subscription_returns_403_cloud_subscription_required(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Cloud subscription is required.',
            'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
        ]);
    }

    public function test_device_from_another_business_returns_403_invalid_sync_device(): void
    {
        $user = User::factory()->create();
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $user->businesses()->attach($businessA, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $businessA->id]);

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Device B',
            'identifier' => 'DEV-B',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $businessA->id,
                'device_identifier' => 'DEV-B',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Invalid sync device.',
            'code' => 'SYNC_DEVICE_INVALID',
        ]);
    }

    public function test_inactive_device_returns_403_device_inactive(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Inactive POS',
            'identifier' => 'POS-INACTIVE',
            'status' => 'inactive',
            'registered_at' => now(),
            'last_seen_at' => null,
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-INACTIVE',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Device is inactive.',
            'code' => 'DEVICE_INACTIVE',
        ]);

        $this->assertNull($device->fresh()->last_seen_at);
    }

    public function test_valid_user_active_cloud_subscription_and_active_device_is_authorized(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Active POS',
            'identifier' => 'POS-ACTIVE',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;
        $requestId = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-ACTIVE',
                'request_id' => $requestId,
                'changes' => [],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'request_id' => $requestId,
                'duplicate' => false,
            ],
        ]);

        $this->assertNotNull($device->fresh()->last_seen_at);
    }
}
