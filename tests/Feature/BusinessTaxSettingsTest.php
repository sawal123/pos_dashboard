<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessTaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_tax_columns_to_businesses(): void
    {
        $this->assertTrue(Schema::hasColumn('businesses', 'tax_enabled'));
        $this->assertTrue(Schema::hasColumn('businesses', 'tax_rate'));

        $business = Business::factory()->create();
        $this->assertFalse($business->tax_enabled);
        $this->assertSame(11.0, (float) $business->tax_rate);
    }

    public function test_owner_can_view_business_settings_with_tax_fields(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('business-settings.edit'));

        $response->assertOk();
        $response->assertSee('Pengaturan Pajak (PB1 / PPN)');
        $response->assertSee('name="tax_enabled"', false);
        $response->assertSee('name="tax_rate"', false);
    }

    public function test_owner_can_update_tax_settings(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.tax.update'), [
                'tax_enabled' => '1',
                'tax_rate' => '10.50',
            ]);

        $response->assertRedirect(route('business-settings.edit'));
        $response->assertSessionHas('status', 'Pengaturan pajak bisnis berhasil disimpan.');

        $business->refresh();
        $this->assertTrue($business->tax_enabled);
        $this->assertSame(10.5, (float) $business->tax_rate);
    }

    public function test_tax_update_validation_rejects_rate_over_100(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.tax.update'), [
                'tax_enabled' => '1',
                'tax_rate' => '105',
            ]);

        $response->assertSessionHasErrors('tax_rate');
    }

    public function test_cashier_cannot_update_tax_settings(): void
    {
        [$cashier, $business] = $this->setupBusinessWithRole(Business::ROLE_CASHIER);

        $response = $this->actingAs($cashier)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.tax.update'), [
                'tax_enabled' => '1',
                'tax_rate' => '10.0',
            ]);

        $response->assertForbidden();
    }

    public function test_pos_checkout_calculates_tax_when_enabled(): void
    {
        [$owner, $business, $outlet, $shift] = $this->setupFullPosContext();

        $business->update([
            'tax_enabled' => true,
            'tax_rate' => 10.0,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Latte',
            'price' => 50000,
            'cost' => 20000,
            'stock' => 20,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        // Subtotal = 50.000, Tax 10% = 5.000, Total = 55.000
        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'payment_method' => 'cash',
                'cash_received' => 60000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('sale.subtotal', 50000);
        $response->assertJsonPath('sale.tax_amount', 5000);
        $response->assertJsonPath('sale.total_amount', 55000);
        $response->assertJsonPath('sale.change_amount', 5000);

        $sale = Sale::where('transaction_number', $response->json('sale.transaction_number'))->firstOrFail();
        $this->assertSame(5000, (int) $sale->tax_amount);
        $this->assertSame(55000, (int) $sale->total_amount);
    }

    public function test_pos_checkout_does_not_charge_tax_when_disabled(): void
    {
        [$owner, $business, $outlet, $shift] = $this->setupFullPosContext();

        $business->update([
            'tax_enabled' => false,
            'tax_rate' => 11.0,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Hitam',
            'price' => 30000,
            'cost' => 10000,
            'stock' => 20,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'payment_method' => 'cash',
                'cash_received' => 30000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('sale.subtotal', 30000);
        $response->assertJsonPath('sale.tax_amount', 0);
        $response->assertJsonPath('sale.total_amount', 30000);
    }

    public function test_mobile_context_returns_tax_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $business = Business::factory()->create([
            'tax_enabled' => true,
            'tax_rate' => 11.0,
        ]);
        $user->businesses()->attach($business->id, ['role' => Business::ROLE_OWNER]);

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.tax_settings.enabled', true);
        $this->assertEquals(11.0, $response->json('data.businesses.0.tax_settings.rate'));
    }

    public function test_mobile_tax_update_endpoint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $business = Business::factory()->create([
            'tax_enabled' => false,
            'tax_rate' => 11.0,
        ]);
        $user->businesses()->attach($business->id, ['role' => Business::ROLE_OWNER]);

        $response = $this->withToken($token)->patchJson("/api/mobile/businesses/{$business->id}/tax-settings", [
            'tax_enabled' => true,
            'tax_rate' => 12.0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.tax_enabled', true);
        $this->assertEquals(12.0, $response->json('data.tax_rate'));

        $business->refresh();
        $this->assertTrue($business->tax_enabled);
        $this->assertEquals(12.0, (float) $business->tax_rate);
    }

    public function test_mobile_tax_update_endpoint_rejects_cashier(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => Business::ROLE_CASHIER]);

        $response = $this->withToken($token)->patchJson("/api/mobile/businesses/{$business->id}/tax-settings", [
            'tax_enabled' => true,
            'tax_rate' => 10.0,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'FORBIDDEN_CASHIER');
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function setupBusinessWithRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => $role]);

        return [$user, $business];
    }

    /**
     * @return array{0: User, 1: Business, 2: Outlet, 3: Shift}
     */
    private function setupFullPosContext(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'OUT-1',
            'status' => 'active',
        ]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        return [$user, $business, $outlet, $shift];
    }
}
