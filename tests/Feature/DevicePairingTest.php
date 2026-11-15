<?php

namespace Tests\Feature;

use App\Exceptions\DevicePairingException;
use App\Models\Business;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Devices\DevicePairingService;
use App\Services\Subscription\CloudDeviceLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PAIR-01 — kasir device pairing dengan kode 4 digit.
 *
 * Aturan yang dijaga ketat di sini:
 *  1. hanya paket Cloud (issue & redeem);
 *  2. tidak untuk owner (owner tidak butuh binding perangkat);
 *  3. pairing hanya untuk role kasir;
 *  4. kode 4 digit, berlaku 30 menit, single-use, bisa dibatalkan;
 *  5. saat redeem, server mencatat nama perangkat, IP, user agent, versi app.
 */
class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Issuing a code (dashboard, owner only)
    // ============================================================

    public function test_owner_can_generate_a_4_digit_pairing_code_for_30_minutes(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->post(route('devices.pairing-code'), ['outlet_id' => $outlet->id]);

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('pairing_code');

        $issued = session('pairing_code');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $issued['code']);

        $pairing = DevicePairingCode::firstOrFail();
        $this->assertSame($business->id, $pairing->business_id);
        $this->assertSame($outlet->id, $pairing->outlet_id);
        $this->assertSame($owner->id, $pairing->created_by);
        $this->assertNull($pairing->used_at);
        $this->assertNull($pairing->revoked_at);

        // Exactly 30 minutes, and the plaintext code is never persisted.
        $this->assertEqualsWithDelta(
            30 * 60,
            now()->diffInSeconds($pairing->expires_at),
            5,
        );
        $this->assertNotSame($issued['code'], $pairing->code_hash);
        $this->assertSame(64, strlen($pairing->code_hash));
    }

    public function test_free_plan_cannot_generate_a_pairing_code(): void
    {
        [$business, $owner] = $this->actingAsRole('owner', cloud: false);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->expectException(DevicePairingException::class);
        $this->expectExceptionMessage('Kode pairing hanya tersedia untuk paket Cloud aktif.');

        app(DevicePairingService::class)->issue($business, $outlet->id, $owner);
    }

    public function test_member_cannot_generate_a_pairing_code(): void
    {
        [$business] = $this->actingAsRole('member');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.pairing-code'), ['outlet_id' => $outlet->id])
            ->assertForbidden();

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_cashier_cannot_generate_a_pairing_code(): void
    {
        [$business] = $this->actingAsRole('cashier');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.pairing-code'), ['outlet_id' => $outlet->id])
            ->assertForbidden();

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_owner_outlet_must_belong_to_the_same_business(): void
    {
        [$business] = $this->actingAsRole('owner');
        $foreignBusiness = Business::factory()->create();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $foreignBusiness->id]);

        $this->post(route('devices.pairing-code'), ['outlet_id' => $foreignOutlet->id])
            ->assertSessionHasErrors('pairing_code');

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_generating_a_new_code_revokes_the_previous_one_for_the_same_outlet(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $service = app(DevicePairingService::class);
        $first = $service->issue($business, $outlet->id, $owner);
        $second = $service->issue($business, $outlet->id, $owner);

        $this->assertNotSame($first['code'], $second['code']);
        $this->assertNotNull($first['pairing']->fresh()->revoked_at);
        $this->assertNull($second['pairing']->fresh()->revoked_at);
        $this->assertCount(1, $service->activeCodes($business));
    }

    public function test_owner_can_revoke_an_unused_code(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $issued = app(DevicePairingService::class)->issue($business, $outlet->id, $owner);

        $this->delete(route('devices.pairing-code.revoke', ['codeId' => $issued['pairing']->id]))
            ->assertRedirect(route('devices.index'));

        $this->assertNotNull($issued['pairing']->fresh()->revoked_at);
    }

    // ============================================================
    // Redeeming a code (cashier, mobile API)
    // ============================================================

    public function test_cashier_can_redeem_a_code_and_the_device_gets_security_metadata(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $code = $this->issueCode($business, $outlet, $owner);

        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->withHeader('User-Agent', 'DevicePairingTest/1.0')
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code, [
                'name' => 'Kasir Depan',
                'app_version' => '1.0.2',
                'platform' => 'android',
                'device_model' => 'Samsung A9',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kasir Depan')
            ->assertJsonPath('data.outlet_id', $outlet->id);

        $device = Device::where('identifier', 'CASHIER-PAIR-01')->firstOrFail();

        $this->assertSame($business->id, $device->business_id);
        $this->assertSame($outlet->id, $device->outlet_id);
        $this->assertSame(Device::STATUS_ACTIVE, $device->status);
        $this->assertSame($cashier->id, $device->paired_user_id);
        $this->assertNotNull($device->paired_at);
        $this->assertSame('1.0.2', $device->app_version);
        $this->assertSame('android', $device->platform);
        $this->assertSame('Model perangkat: Samsung A9', $device->notes);
        $this->assertNotNull($device->last_ip);
        $this->assertSame('DevicePairingTest/1.0', $device->user_agent);

        $pairing = DevicePairingCode::firstOrFail();
        $this->assertNotNull($pairing->used_at);
        $this->assertSame($device->id, $pairing->used_device_id);
        $this->assertSame($cashier->id, $pairing->used_by);
    }

    public function test_a_code_can_only_be_redeemed_once(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertOk();

        $second = $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code));

        $second->assertStatus(409)->assertJson(['code' => 'PAIRING_CODE_ALREADY_USED']);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        $this->travel(31)->minutes();

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertStatus(410)
            ->assertJson(['code' => 'PAIRING_CODE_EXPIRED']);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_revoked_code_is_rejected(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $service = app(DevicePairingService::class);
        $issued = $service->issue($business, $outlet->id, $owner);
        $service->revoke($business, $issued['pairing']->id);

        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $issued['code']))
            ->assertStatus(410)
            ->assertJson(['code' => 'PAIRING_CODE_REVOKED']);
    }

    public function test_wrong_codes_are_rate_limited(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);
        $token = $this->apiTokenFor($cashier);

        for ($attempt = 1; $attempt <= DevicePairingService::MAX_ATTEMPTS; $attempt++) {
            $this->withToken($token)
                ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, '0000'))
                ->assertStatus(422)
                ->assertJson(['code' => 'PAIRING_CODE_INVALID']);
        }

        $this->withToken($token)
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, '0000'))
            ->assertStatus(429)
            ->assertJson(['code' => 'PAIRING_RATE_LIMITED']);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_owner_cannot_redeem_a_pairing_code(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);

        $this->withToken($this->apiTokenFor($owner))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertStatus(403)
            ->assertJson(['code' => 'PAIRING_CASHIER_ONLY']);
    }

    public function test_free_plan_cannot_redeem(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $issued = app(DevicePairingService::class)->issue($business, $outlet->id, $owner);

        // Cloud expires after the code was issued.
        $business->subscription->update(['status' => 'expired', 'expires_at' => now()->subDay()]);

        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $issued['code']))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_inactive_device_cannot_be_repaired(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Lama',
            'identifier' => 'CASHIER-PAIR-01',
            'status' => Device::STATUS_INACTIVE,
            'registered_at' => now(),
        ]);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);
    }

    public function test_device_registered_on_another_outlet_cannot_be_repaired(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $otherOutlet = Outlet::factory()->create(['business_id' => $business->id]);
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $otherOutlet->id,
            'name' => 'Outlet Lain',
            'identifier' => 'CASHIER-PAIR-01',
            'status' => Device::STATUS_ACTIVE,
            'registered_at' => now(),
        ]);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertStatus(409)
            ->assertJson(['code' => 'DEVICE_OUTLET_MISMATCH']);
    }

    public function test_cloud_device_limit_is_enforced_on_pairing(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        $limit = app(CloudDeviceLimit::class)->limit();
        $this->assertGreaterThan(0, $limit);

        for ($i = 1; $i <= $limit; $i++) {
            Device::create([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'name' => 'Device '.$i,
                'identifier' => 'FILLER-'.$i,
                'status' => Device::STATUS_ACTIVE,
                'registered_at' => now(),
            ]);
        }

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_DEVICE_LIMIT_REACHED']);
    }

    public function test_pairing_is_tenant_isolated(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);

        // A cashier of another business cannot use this code.
        $otherBusiness = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $otherBusiness->id]);
        $foreignCashier = $this->cashierFor($otherBusiness);

        $this->withToken($this->apiTokenFor($foreignCashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($otherBusiness, $code))
            ->assertStatus(422)
            ->assertJson(['code' => 'PAIRING_CODE_INVALID']);

        $this->assertDatabaseCount('devices', 0);
        $this->assertNull(DevicePairingCode::firstOrFail()->used_at);
    }

    // ============================================================
    // Sync contract: cashier must be paired, owner does not
    // ============================================================

    public function test_unpaired_cashier_cannot_sync(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier='.'UNPAIRED-'.Str::random(4))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_NOT_REGISTERED']);

        $this->assertDatabaseMissing('devices', ['outlet_id' => $outlet->id]);
    }

    public function test_paired_cashier_can_sync(): void
    {
        [$business, $owner, $outlet] = $this->ownerWithOutlet();
        $code = $this->issueCode($business, $outlet, $owner);
        $cashier = $this->cashierFor($business);

        $this->withToken($this->apiTokenFor($cashier))
            ->postJson('/api/mobile/devices/pair', $this->pairPayload($business, $code))
            ->assertOk();

        $this->withToken($this->apiTokenFor($cashier))
            ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier=CASHIER-PAIR-01&after=0&limit=10')
            ->assertOk();
    }

    public function test_owner_without_a_device_row_still_syncs(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        Outlet::factory()->create(['business_id' => $business->id]);

        $this->withToken($this->apiTokenFor($owner))
            ->getJson('/api/sync/pull?business_id='.$business->id.'&device_identifier=OWNER-'.Str::random(4).'&after=0&limit=10')
            ->assertOk();

        // Owner devices are still provisioned lazily on the server.
        $this->assertDatabaseCount('devices', 1);
    }

    // ============================================================
    // Dashboard surface
    // ============================================================

    public function test_devices_page_exposes_the_pairing_modal_for_the_owner(): void
    {
        [$business] = $this->actingAsRole('owner');
        Outlet::factory()->create(['business_id' => $business->id]);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertSee('id="pairingModal"', false);
        $response->assertSee('id="pairCashierBtn"', false);
        $response->assertSee('Kode Kasir');
    }

    public function test_member_cannot_reach_the_devices_page_at_all(): void
    {
        [$business] = $this->actingAsRole('member');
        Outlet::factory()->create(['business_id' => $business->id]);

        $this->get(route('devices.index'))->assertForbidden();
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: Business, 1: User} [business, owner]
     */
    private function actingAsRole(string $role, bool $cloud = true): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);

        if ($cloud) {
            Subscription::factory()->cloud()->create(['business_id' => $business->id]);
            $business->refresh();
        }

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }

    /**
     * @return array{0: Business, 1: User, 2: Outlet}
     */
    private function ownerWithOutlet(): array
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return [$business, $owner, $outlet];
    }

    private function issueCode(Business $business, Outlet $outlet, User $owner): string
    {
        return app(DevicePairingService::class)->issue($business, $outlet->id, $owner)['code'];
    }

    private function cashierFor(Business $business): User
    {
        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, ['role' => 'cashier']);

        return $cashier;
    }

    /**
     * A Sanctum bearer token also clears any `actingAs` session user, so the
     * API request is authorized by the token owner, not by the dashboard owner.
     */
    private function apiTokenFor(User $user): string
    {
        Auth::forgetGuards();

        return $user->createToken('mobile-api', ['mobile'])->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pairPayload(Business $business, string $code, array $overrides = []): array
    {
        return array_merge([
            'business_id' => $business->id,
            'code' => $code,
            'device_identifier' => 'CASHIER-PAIR-01',
            'name' => 'Kasir Pairing',
            'platform' => 'android',
            'app_version' => '1.0.1',
            'device_model' => 'Tablet Uji',
        ], $overrides);
    }
}
