<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Catalog\OutletPricing;
use App\Services\Dashboard\OutletManagementService;
use App\Services\Pos\PosCatalogData;
use App\Services\Subscription\PremiumSubscriptionActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOutletBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_outlet_is_primary_and_new_branch_starts_as_pending_payment(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => Business::ROLE_OWNER]);

        $primary = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Utama']);
        $this->assertTrue($primary->fresh()->is_primary);

        $branch = app(OutletManagementService::class)->createBranch($business, [
            'name' => 'Cabang Kemang',
            'address' => 'Jl. Kemang Raya No. 10',
            'phone' => '081234567890',
        ]);

        $this->assertFalse($branch->is_primary);
        $this->assertSame(Outlet::STATUS_PENDING_PAYMENT, $branch->status);
        $this->assertFalse($branch->hasCloudAccess());
    }

    public function test_owner_can_create_and_edit_branch_from_dashboard_and_member_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->create(['status' => 'active']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);

        Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Utama']);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('outlets.store'), ['name' => 'Cabang Ilegal'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('outlets.store'), [
                'name' => 'Cabang Tebet',
                'code' => 'CBG-TBT',
                'address' => 'Tebet Timur',
            ])
            ->assertRedirect(route('outlets.index'));

        $branch = Outlet::query()->where('business_id', $business->id)->where('code', 'CBG-TBT')->first();
        $this->assertNotNull($branch);
        $this->assertSame(Outlet::STATUS_PENDING_PAYMENT, $branch->status);

        // Ensure configured platform prices render in the branch checkout modal
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['code' => 'cloud'],
            ['name' => 'Cloud', 'is_active' => true],
        );
        SubscriptionPlanPrice::query()->updateOrCreate(
            ['subscription_plan_id' => $plan->id, 'billing_period' => 'monthly', 'currency' => 'IDR'],
            ['price_minor' => 49000, 'is_active' => true],
        );
        SubscriptionPlanPrice::query()->updateOrCreate(
            ['subscription_plan_id' => $plan->id, 'billing_period' => 'yearly', 'currency' => 'IDR'],
            ['price_minor' => 490000, 'is_active' => true],
        );

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('outlets.index'))
            ->assertOk()
            ->assertSee('49.000')
            ->assertSee('490.000')
            ->assertDontSee('Harga paket belum dikonfigurasi oleh operator platform.');
    }

    public function test_branch_payment_activates_branch_subscription_without_overwriting_primary(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['status' => 'active']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $primary = Outlet::factory()->create(['business_id' => $business->id]);
        $primaryExpiry = now()->addDays(10);
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $primaryExpiry,
        ]);

        $branch = app(OutletManagementService::class)->createBranch($business, ['name' => 'Cabang Baru']);
        $this->assertFalse($branch->hasCloudAccess());

        $payment = SubscriptionPayment::query()->create([
            'business_id' => $business->id,
            'outlet_id' => $branch->id,
            'user_id' => $owner->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-branch-test-1',
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 50000,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        app(PremiumSubscriptionActivator::class)->activatePaidPayment($payment);

        $branch->refresh();
        $this->assertSame(Outlet::STATUS_ACTIVE, $branch->status);
        $this->assertTrue($branch->hasCloudAccess());
        $this->assertSame((int) $primaryExpiry->timestamp, (int) $business->fresh()->subscription->expires_at->timestamp);

        // If primary expires, business retains dashboard access while branch is active
        $business->subscription->update(['status' => Subscription::STATUS_EXPIRED, 'expires_at' => now()->subDay()]);
        $this->assertFalse($primary->fresh()->hasCloudAccess());
        $this->assertTrue($branch->fresh()->hasCloudAccess());
        $this->assertTrue($business->fresh()->hasCloudAccess());
    }

    public function test_master_vs_branch_specific_product_prices_and_pos_catalog(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['business_type' => 'cafe']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Toko A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang B', 'code' => 'CBG-B']);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Es Kopi Susu',
            'price' => 18000,
            'promo_price' => null,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $pricing = app(OutletPricing::class);
        $this->assertSame(18000, $pricing->forProduct($product, $outletA)['price']);
        $this->assertSame(18000, $pricing->forProduct($product, $outletB)['price']);

        $pricing->syncProductOverrides($product, [
            $outletB->id => ['price' => 22000, 'is_available' => true],
        ]);

        $this->assertSame(18000, $pricing->forProduct($product->fresh(), $outletA)['price']);
        $this->assertSame(22000, $pricing->forProduct($product->fresh(), $outletB)['price']);

        $catalogA = app(PosCatalogData::class)->get($business, $outletA->id);
        $catalogB = app(PosCatalogData::class)->get($business, $outletB->id);

        $this->assertSame(18000, collect($catalogA['products'])->firstWhere('id', $product->id)['price']);
        $this->assertSame(22000, collect($catalogB['products'])->firstWhere('id', $product->id)['price']);
    }

    public function test_promos_can_apply_to_all_outlets_or_selected_outlets(): void
    {
        $business = Business::factory()->create(['business_type' => 'cafe']);
        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Utama']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang B', 'code' => 'B']);

        $promoAll = Promo::create([
            'business_id' => $business->id,
            'name' => 'Promo Semua Cabang',
            'discount_type' => 'percent',
            'discount_value' => 1000,
            'scope' => 'all',
            'outlet_scope' => Promo::OUTLET_SCOPE_ALL,
            'status' => 'active',
        ]);

        $promoBranchOnly = Promo::create([
            'business_id' => $business->id,
            'name' => 'Promo Khusus Cabang B',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'scope' => 'all',
            'outlet_scope' => Promo::OUTLET_SCOPE_SELECTED,
            'status' => 'active',
        ]);
        $promoBranchOnly->outlets()->sync([$outletB->id => ['business_id' => $business->id]]);

        $forA = Promo::query()->where('business_id', $business->id)->forOutlet($outletA->id)->pluck('id')->all();
        $forB = Promo::query()->where('business_id', $business->id)->forOutlet($outletB->id)->pluck('id')->all();

        $this->assertContains($promoAll->id, $forA);
        $this->assertNotContains($promoBranchOnly->id, $forA);
        $this->assertContains($promoAll->id, $forB);
        $this->assertContains($promoBranchOnly->id, $forB);
    }

    public function test_outlet_specific_promo_price_overrides_master_and_reflects_in_catalog(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['business_type' => 'cafe']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Toko Utama', 'is_primary' => true]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Kampus', 'code' => 'KMP']);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Americano Special',
            'price' => 18000,
            'promo_price' => 16000,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $pricing = app(OutletPricing::class);

        // Before override: both outlets follow master (price 18000, promo 16000)
        $pricingA = $pricing->forProduct($product, $outletA);
        $this->assertSame(18000, $pricingA['price']);
        $this->assertSame(16000, $pricingA['promo_price']);

        // Set branch specific price & promo for outlet B (regular 20000, promo 14000)
        $pricing->syncProductOverrides($product, [
            $outletB->id => [
                'price' => 20000,
                'promo_price' => 14000,
                'is_available' => true,
            ],
        ]);

        $pricingB = $pricing->forProduct($product->fresh(), $outletB);
        $this->assertSame(20000, $pricingB['price']);
        $this->assertSame(14000, $pricingB['promo_price']);

        // Check PosCatalogData reflects effective price & discount
        $catalogA = app(PosCatalogData::class)->get($business, $outletA->id);
        $catalogB = app(PosCatalogData::class)->get($business, $outletB->id);

        $itemA = collect($catalogA['products'])->firstWhere('id', $product->id);
        $itemB = collect($catalogB['products'])->firstWhere('id', $product->id);

        $this->assertSame(18000, $itemA['price']);
        $this->assertSame(16000, $itemA['promo_price']);
        $this->assertSame(16000, $itemA['effective_price']);

        $this->assertSame(20000, $itemB['price']);
        $this->assertSame(14000, $itemB['promo_price']);
        $this->assertSame(14000, $itemB['effective_price']);
    }
}
