<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * INT-03 — read-only sync request status API.
 *
 * `GET /api/sync/requests/{request_id}/status` lets POS Mobile learn whether a
 * push whose response was lost has already been committed. Authorization uses
 * SYNC_PULL so a role change after the lost response does not hide the truth.
 * A `not_found` answer never means "it will not commit".
 */
class SyncRequestStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_committed_request_reports_committed_with_its_processed_at(): void
    {
        $env = $this->makeEnvironment('cashier');
        $requestId = (string) Str::uuid();
        $record = $this->commitRequest($env, $requestId, Carbon::parse('2026-09-28 08:15:30'));

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'request_id' => $requestId,
                    'status' => 'committed',
                    'processed_at' => $record->processed_at->toIso8601String(),
                ],
            ]);
    }

    public function test_an_unknown_request_reports_not_found_with_null_processed_at(): void
    {
        $env = $this->makeEnvironment('cashier');

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, (string) Str::uuid()))
            ->assertOk()
            ->assertJsonPath('data.status', 'not_found')
            ->assertJsonPath('data.processed_at', null);
    }

    public function test_never_returns_the_request_payload(): void
    {
        $env = $this->makeEnvironment('cashier');
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        $response = $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertOk();

        // Only the three contract fields are exposed — no body, no changes, no
        // device/business internals.
        $this->assertSame(
            ['data'],
            array_keys($response->json()),
        );
        $this->assertSame(
            ['request_id', 'status', 'processed_at'],
            array_keys($response->json('data')),
        );
    }

    // ============================================================
    // Role and membership changes
    // ============================================================

    public function test_a_member_downgraded_to_cashier_can_still_check_with_the_same_token(): void
    {
        $env = $this->makeEnvironment('member');
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        // The role changes after the (lost) push; the already-issued token stays.
        $env['business']->users()->updateExistingPivot($env['user']->id, ['role' => 'cashier']);

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertOk()
            ->assertJsonPath('data.status', 'committed');
    }

    public function test_an_owner_and_a_cashier_can_check_their_own_device(): void
    {
        foreach (['owner', 'member', 'cashier'] as $role) {
            Auth::forgetGuards();
            $env = $this->makeEnvironment($role);
            $requestId = (string) Str::uuid();
            $this->commitRequest($env, $requestId);

            $this->withToken($env['token'])
                ->getJson($this->statusUrl($env, $requestId))
                ->assertOk()
                ->assertJsonPath('data.status', 'committed');
        }
    }

    public function test_a_revoked_membership_is_denied(): void
    {
        $env = $this->makeEnvironment('cashier');
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        $env['business']->users()->detach($env['user']->id);

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertStatus(403)
            ->assertJson(['code' => 'BUSINESS_ACCESS_DENIED']);
    }

    public function test_an_unsupported_role_is_denied(): void
    {
        $env = $this->makeEnvironment('supervisor');
        $requestId = (string) Str::uuid();

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    // ============================================================
    // Device and subscription gates
    // ============================================================

    public function test_an_inactive_device_is_denied(): void
    {
        $env = $this->makeEnvironment('cashier');
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        $env['device']->update(['status' => 'inactive']);

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);
    }

    public function test_a_device_from_another_business_is_denied(): void
    {
        $env = $this->makeEnvironment('cashier');
        $other = $this->makeEnvironment('cashier');

        $this->withToken($env['token'])
            ->getJson('/api/sync/requests/'.Str::uuid().'/status?'.http_build_query([
                'business_id' => $env['business']->id,
                'device_identifier' => $other['device']->identifier,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'SYNC_DEVICE_INVALID']);
    }

    public function test_an_inactive_subscription_is_denied(): void
    {
        $env = $this->makeEnvironment('cashier', cloud: false);
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    // ============================================================
    // Validation and authentication
    // ============================================================

    public function test_an_invalid_uuid_is_rejected_with_a_validation_error(): void
    {
        $env = $this->makeEnvironment('cashier');

        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, 'not-a-uuid'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('request_id');
    }

    public function test_a_request_without_a_token_is_unauthorized(): void
    {
        $this->getJson('/api/sync/requests/'.Str::uuid().'/status?business_id=1&device_identifier=POS-01')
            ->assertStatus(401);
    }

    public function test_a_token_without_the_mobile_ability_is_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $webToken = $env['user']->createToken('web-api', ['web'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$webToken)
            ->getJson($this->statusUrl($env, (string) Str::uuid()))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_TOKEN_REQUIRED']);
    }

    // ============================================================
    // Tenant / device isolation
    // ============================================================

    public function test_it_never_reveals_another_tenants_request(): void
    {
        $env = $this->makeEnvironment('owner');
        $other = $this->makeEnvironment('owner');

        $foreignRequestId = (string) Str::uuid();
        $this->commitRequest($other, $foreignRequestId);

        // Same id, but requested by a device of a different business: it must be
        // indistinguishable from a missing request.
        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $foreignRequestId))
            ->assertOk()
            ->assertJsonPath('data.status', 'not_found')
            ->assertJsonPath('data.processed_at', null);
    }

    public function test_it_never_reveals_another_devices_request_in_the_same_business(): void
    {
        $env = $this->makeEnvironment('owner');
        $sibling = Device::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'name' => 'Sibling POS',
            'identifier' => 'POS-SIBLING-'.Str::upper(Str::random(6)),
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $requestId = (string) Str::uuid();
        SyncRequest::create([
            'business_id' => $env['business']->id,
            'device_id' => $sibling->id,
            'request_id' => $requestId,
            'processed_at' => now(),
        ]);

        // The resolved device is the caller's own device, so another device's
        // request is reported as not_found rather than exposed.
        $this->withToken($env['token'])
            ->getJson($this->statusUrl($env, $requestId))
            ->assertOk()
            ->assertJsonPath('data.status', 'not_found');
    }

    // ============================================================
    // Read-only guarantee
    // ============================================================

    public function test_it_has_no_side_effects_on_stock_cash_or_sync_state(): void
    {
        $env = $this->makeEnvironment('owner');

        $product = Product::create([
            'business_id' => $env['business']->id,
            'name' => 'Widget',
            'sku' => 'W-'.Str::upper(Str::random(6)),
            'price' => 5000,
            'stock' => 7,
        ]);
        StockMovement::create([
            'business_id' => $env['business']->id,
            'product_id' => $product->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 7,
            'occurred_at' => now(),
        ]);
        CashLedger::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'type' => 'in',
            'amount' => 12345,
            'category' => 'cash_in',
            'occurred_at' => now(),
        ]);
        $requestId = (string) Str::uuid();
        $this->commitRequest($env, $requestId);

        $before = [
            'stock' => (float) $product->fresh()->stock,
            'movements' => StockMovement::count(),
            'cash' => CashLedger::count(),
            'requests' => SyncRequest::count(),
        ];

        $this->withToken($env['token'])->getJson($this->statusUrl($env, $requestId))->assertOk();
        $this->withToken($env['token'])->getJson($this->statusUrl($env, (string) Str::uuid()))->assertOk();

        $this->assertSame($before['stock'], (float) $product->fresh()->stock);
        $this->assertSame($before['movements'], StockMovement::count());
        $this->assertSame($before['cash'], CashLedger::count());
        $this->assertSame($before['requests'], SyncRequest::count());
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{user: User, business: Business, outlet: Outlet, device: Device, token: string}
     */
    private function makeEnvironment(string $role, bool $cloud = true): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);
        $cloud
            ? Subscription::factory()->cloud()->create(['business_id' => $business->id])
            : Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS '.$role,
            'identifier' => 'POS-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outlet', 'device', 'token');
    }

    /**
     * @param  array{user: User, business: Business, outlet: Outlet, device: Device, token: string}  $env
     */
    private function commitRequest(array $env, string $requestId, ?Carbon $processedAt = null): SyncRequest
    {
        return SyncRequest::create([
            'business_id' => $env['business']->id,
            'device_id' => $env['device']->id,
            'request_id' => $requestId,
            'processed_at' => $processedAt ?? now(),
        ]);
    }

    /**
     * @param  array{user: User, business: Business, outlet: Outlet, device: Device, token: string}  $env
     */
    private function statusUrl(array $env, string $requestId): string
    {
        return '/api/sync/requests/'.$requestId.'/status?'.http_build_query([
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
        ]);
    }
}
