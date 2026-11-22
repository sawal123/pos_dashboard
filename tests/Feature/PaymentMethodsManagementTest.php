<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\PaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusinessWithTwoOutlets(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create([
            'name' => 'Temuan Space',
            'business_type' => 'cafe',
        ]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outletA = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Laksana',
            'code' => 'LKS-01',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $outletB = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang USU',
            'code' => 'USU-01',
            'is_primary' => false,
            'status' => 'active',
        ]);

        return [$business, $owner, $outletA, $outletB];
    }

    public function test_index_auto_seeds_default_payment_methods_and_displays_outlet_matrix(): void
    {
        [$business, $owner, $outletA, $outletB] = $this->setupBusinessWithTwoOutlets();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('payment-methods.index'));

        $response->assertOk()
            ->assertSee('Metode Pembayaran')
            ->assertSee('Tunai')
            ->assertSee('QRIS')
            ->assertSee('Transfer Bank')
            ->assertSee('Kartu / Debit')
            ->assertSee($outletA->name)
            ->assertSee($outletB->name);

        $this->assertEquals(4, PaymentMethod::where('business_id', $business->id)->count());
    }

    public function test_owner_can_create_custom_method_and_toggle_per_outlet(): void
    {
        [$business, $owner, $outletA, $outletB] = $this->setupBusinessWithTwoOutlets();

        // Create GoPay enabled ONLY on outletA
        $res = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('payment-methods.store'), [
                'name' => 'GoPay',
                'category' => PaymentMethod::CATEGORY_EWALLET,
                'is_cash' => '0',
                'description' => 'QR GoPay Merchant',
                'outlet_selection_present' => '1',
                'enabled_outlet_ids' => [$outletA->id],
            ]);

        $res->assertRedirect(route('payment-methods.index'));
        $res->assertSessionHasNoErrors();

        $gopay = PaymentMethod::where('business_id', $business->id)->where('code', 'gopay')->firstOrFail();
        $this->assertFalse($gopay->is_cash);

        $service = app(PaymentMethodService::class);
        $this->assertTrue($service->forOutlet($business, $outletA)->contains('id', $gopay->id));
        $this->assertFalse($service->forOutlet($business, $outletB)->contains('id', $gopay->id));

        // Now toggle GoPay ON for outletB via AJAX
        $toggleRes = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patchJson(route('payment-methods.toggle-outlet', [$gopay, $outletB]), [
                'is_enabled' => true,
            ]);

        $toggleRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_enabled', true);

        $this->assertTrue($service->forOutlet($business, $outletB)->contains('id', $gopay->id));
    }

    public function test_pos_checkout_accepts_custom_payment_method_and_skips_cash_ledger_for_non_cash(): void
    {
        [$business, $owner, $outletA] = $this->setupBusinessWithTwoOutlets();

        $service = app(PaymentMethodService::class);
        $service->ensureDefaults($business);

        $shopeefood = PaymentMethod::create([
            'business_id' => $business->id,
            'name' => 'ShopeeFood',
            'code' => 'shopeefood',
            'category' => PaymentMethod::CATEGORY_ONLINE_FOOD,
            'is_cash' => false,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHF-PM-01',
            'opened_by' => $owner->id,
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
            'sync_id' => (string) Str::uuid(),
        ]);

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Es Kopi Susu',
            'sku' => 'SKU-EKS',
            'cost' => 8000,
            'price' => 20000,
            'stock' => 20,
            'min_stock' => 0,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
            'status' => 'active',
            'is_sellable' => true,
            'sync_id' => (string) Str::uuid(),
        ]);

        $clientSaleId = (string) Str::uuid();
        $res = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'outlet_id' => $outletA->id,
                'shift_id' => $shift->id,
                'payment_method' => $shopeefood->code,
                'client_sale_id' => $clientSaleId,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sale.payment_method', 'shopeefood')
            ->assertJsonPath('sale.total_amount', 40000);

        // Non-cash custom method should NOT create CashLedger entry
        $this->assertEquals(0, CashLedger::where('sale_sync_id', $clientSaleId)->count());
    }

    public function test_mobile_context_exposes_payment_methods_scoped_to_cashier_outlet(): void
    {
        [$business, $owner, $outletA, $outletB] = $this->setupBusinessWithTwoOutlets();

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, [
            'role' => Business::ROLE_CASHIER,
            'outlet_id' => $outletB->id,
        ]);

        // Create OVO enabled ONLY on outletA (disabled on outletB)
        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('payment-methods.store'), [
                'name' => 'OVO',
                'category' => PaymentMethod::CATEGORY_EWALLET,
                'outlet_selection_present' => '1',
                'enabled_outlet_ids' => [$outletA->id],
            ]);

        app('auth')->forgetGuards();
        $token = $cashier->createToken('mobile-api', ['mobile'])->plainTextToken;
        $ctxRes = $this->withToken($token)->getJson('/api/mobile/context');
        $ctxRes->assertOk();

        $methods = collect($ctxRes->json('data.businesses.0.payment_methods'));
        $this->assertTrue($methods->contains('code', 'cash'));
        $this->assertFalse($methods->contains('code', 'ovo'));
    }

    public function test_system_cash_method_cannot_be_deleted_but_custom_method_can(): void
    {
        [$business, $owner] = $this->setupBusinessWithTwoOutlets();

        $service = app(PaymentMethodService::class);
        $methods = $service->ensureDefaults($business);
        $cash = $methods->firstWhere('code', 'cash');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('payment-methods.destroy', $cash))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseHas('payment_methods', ['id' => $cash->id]);

        $custom = PaymentMethod::create([
            'business_id' => $business->id,
            'name' => 'DANA',
            'code' => 'dana',
            'category' => PaymentMethod::CATEGORY_EWALLET,
            'is_cash' => false,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->delete(route('payment-methods.destroy', $custom))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('payment_methods', ['id' => $custom->id]);
    }
}
