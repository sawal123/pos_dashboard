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

/**
 * DASH-14 — GET /api/mobile/context business type compatibility.
 *
 * `business_type` is added as an additive/optional field. Every pre-existing
 * key keeps its name and shape so an existing POS Mobile client is unaffected,
 * and a business type never opens sync access that RBAC would deny.
 */
class MobileBusinessTypeContextTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} [user, mobile token] */
    private function userWithMobileToken(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return [$user, $token];
    }

    private function attachUser(User $user, Business $business, string $role = 'owner'): void
    {
        $user->businesses()->attach($business->id, ['role' => $role]);
    }

    // =========================================================================
    // Contract compatibility
    // =========================================================================

    public function test_context_keeps_the_existing_contract_and_only_adds_one_field(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->laundry()->create();
        $this->attachUser($user, $business);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'businesses' => [
                    '*' => ['id', 'name', 'business_type', 'subscription', 'cloud_access', 'outlets', 'device_context'],
                ],
            ],
        ]);

        /** @var array<int, array<string, mixed>> $businesses */
        $businesses = $response->json('data.businesses');
        $this->assertCount(1, $businesses);

        // No existing key was removed or renamed — only `business_type` added.
        $keys = array_keys($businesses[0]);
        sort($keys);
        $this->assertSame(
            ['business_type', 'cloud_access', 'device_context', 'id', 'name', 'outlets', 'subscription'],
            $keys,
        );
        $this->assertNull($businesses[0]['device_context']);
    }

    public function test_context_returns_the_canonical_business_type(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->cafe()->create(['name' => 'Kopi Sore']);
        $this->attachUser($user, $business);

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.name', 'Kopi Sore');
        $response->assertJsonPath('data.businesses.0.business_type', 'cafe');
    }

    public function test_context_returns_null_type_when_unknown(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $this->attachUser($user, $business);

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data.businesses.0');
        $this->assertArrayHasKey('business_type', $payload);
        $this->assertNull($payload['business_type']);
    }

    public function test_context_normalizes_a_legacy_stored_value(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->create();
        $business->forceFill(['business_type' => 'restoran'])->save();
        $this->attachUser($user, $business);

        $this->withToken($token)->getJson('/api/mobile/context')
            ->assertOk()
            ->assertJsonPath('data.businesses.0.business_type', 'cafe');
    }

    public function test_context_reports_each_business_type_independently(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $cafe = Business::factory()->cafe()->create();
        $laundry = Business::factory()->laundry()->create();
        $grosir = Business::factory()->grosir()->create();
        $unknown = Business::factory()->create();

        foreach ([$cafe, $laundry, $grosir, $unknown] as $business) {
            $this->attachUser($user, $business);
        }

        $response = $this->withToken($token)->getJson('/api/mobile/context');
        $response->assertOk();

        /** @var array<int, array<string, mixed>> $businesses */
        $businesses = $response->json('data.businesses');
        $byId = collect($businesses)->keyBy('id')->map(fn (array $b): mixed => $b['business_type'])->all();

        $this->assertSame('cafe', $byId[$cafe->id]);
        $this->assertSame('laundry', $byId[$laundry->id]);
        $this->assertSame('grosir', $byId[$grosir->id]);
        $this->assertNull($byId[$unknown->id]);
    }

    public function test_mobile_client_is_not_required_to_send_a_business_type(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->laundry()->create();
        $this->attachUser($user, $business);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Uji',
            'identifier' => 'DASH14-POS',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // A legacy request that knows nothing about business types still works.
        $response = $this->withToken($token)
            ->getJson('/api/mobile/context?device_identifier=DASH14-POS');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.business_type', 'laundry');
        $response->assertJsonPath('data.businesses.0.device_context.identifier', 'DASH14-POS');
    }

    // =========================================================================
    // A business type never grants sync access
    // =========================================================================

    public function test_cashier_sync_stays_denied_regardless_of_business_type(): void
    {
        [$user, $token] = $this->userWithMobileToken();

        $business = Business::factory()->laundry()->create();
        $this->attachUser($user, $business, 'cashier');
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Kasir',
            'identifier' => 'DASH14-CASHIER',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // The read-only context is still available (documented boundary).
        $this->withToken($token)->getJson('/api/mobile/context')
            ->assertOk()
            ->assertJsonPath('data.businesses.0.business_type', 'laundry');

        // ...but `laundry` must not open the sync contract for a cashier.
        $this->withToken($token)->postJson('/api/sync/push', [
            'business_id' => $business->id,
            'device_identifier' => 'DASH14-CASHIER',
            'request_id' => (string) Str::uuid(),
            'changes' => [],
        ])->assertStatus(403);

        $this->withToken($token)
            ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier=DASH14-CASHIER')
            ->assertStatus(403);
    }
}
