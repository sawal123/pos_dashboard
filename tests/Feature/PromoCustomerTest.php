<?php

namespace Tests\Feature;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoTarget;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Promo\PromoEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromoCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_can_create_promo_with_customer_scope_for_all_customers(): void
    {
        [$business, $owner] = $this->setupTenant();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), [
                'name' => 'Diskon Member Toko',
                'code' => 'MEMBER10',
                'discount_type' => PromoDiscountType::Percent->value,
                'discount_value' => 1000, // 10%
                'scope' => PromoScope::Customer->value,
                'min_purchase' => 0,
                'status' => PromoStatus::Active->value,
            ]);

        $response->assertRedirect(route('promos.index'));

        $promo = Promo::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('Diskon Member Toko', $promo->name);
        $this->assertSame(PromoScope::Customer->value, $promo->scope);
        $this->assertCount(0, $promo->targets);
    }

    public function test_02_can_create_promo_with_specific_customer_targets(): void
    {
        [$business, $owner] = $this->setupTenant();

        $cust1 = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Budi']);
        $cust2 = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Siti']);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), [
                'name' => 'Diskon Khusus Budi',
                'code' => 'BUDIVIP',
                'discount_type' => PromoDiscountType::Fixed->value,
                'discount_value' => 25000,
                'scope' => PromoScope::Customer->value,
                'min_purchase' => 50000,
                'target_ids' => [$cust1->id],
                'status' => PromoStatus::Active->value,
            ]);

        $response->assertRedirect(route('promos.index'));

        $promo = Promo::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame(PromoScope::Customer->value, $promo->scope);
        $this->assertCount(1, $promo->targets);

        $target = $promo->targets->first();
        $this->assertSame(PromoScope::Customer->value, $target->target_type);
        $this->assertSame($cust1->id, (int) $target->target_id);
    }

    public function test_03_cannot_target_customer_from_another_business(): void
    {
        [$businessA, $owner] = $this->setupTenant();

        // Business B owned by the same owner
        $businessB = Business::factory()->create();
        $businessB->users()->attach($owner->id, ['role' => 'owner']);
        $custB = Customer::factory()->create(['business_id' => $businessB->id, 'name' => 'Pelanggan Toko B']);

        // Attempt to create promo in Business A targeting Customer of Business B
        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->post(route('promos.store'), [
                'name' => 'Promo Bocor',
                'discount_type' => PromoDiscountType::Percent->value,
                'discount_value' => 1000,
                'scope' => PromoScope::Customer->value,
                'target_ids' => [$custB->id],
                'status' => PromoStatus::Active->value,
            ]);

        $response->assertSessionHasErrors(['targets.0.id']);
        $this->assertDatabaseMissing('promos', ['name' => 'Promo Bocor']);
    }

    public function test_04_promo_evaluator_requires_customer_for_customer_scoped_promo(): void
    {
        [$business] = $this->setupTenant();

        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Promo All Customers',
            'discount_type' => PromoDiscountType::Percent->value,
            'discount_value' => 1000, // 10%
            'scope' => PromoScope::Customer->value,
            'min_purchase' => 0,
            'status' => PromoStatus::Active->value,
        ]);

        $evaluator = new PromoEvaluator;
        $lines = [
            ['product_id' => 1, 'category_id' => null, 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000],
        ];

        // 1. Without customer
        $resultWithoutCustomer = $evaluator->evaluate($promo, 50000, $lines, Carbon::now(), null);
        $this->assertFalse($resultWithoutCustomer['eligible']);
        $this->assertSame('promo_customer_required', $resultWithoutCustomer['reason']);

        // 2. With customer
        $cust = Customer::factory()->create(['business_id' => $business->id]);
        $resultWithCustomer = $evaluator->evaluate($promo, 50000, $lines, Carbon::now(), $cust->id);
        $this->assertTrue($resultWithCustomer['eligible']);
        $this->assertSame(5000, $resultWithCustomer['discount']);
    }

    public function test_05_promo_evaluator_enforces_specific_customer_targets(): void
    {
        [$business] = $this->setupTenant();

        $custTarget = Customer::factory()->create(['business_id' => $business->id, 'name' => 'VIP Customer']);
        $custOther = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Regular Customer']);

        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Promo VIP Only',
            'discount_type' => PromoDiscountType::Fixed->value,
            'discount_value' => 10000,
            'scope' => PromoScope::Customer->value,
            'min_purchase' => 20000,
            'status' => PromoStatus::Active->value,
        ]);

        PromoTarget::create([
            'business_id' => $business->id,
            'promo_id' => $promo->id,
            'target_type' => PromoScope::Customer->value,
            'target_id' => $custTarget->id,
        ]);

        $evaluator = new PromoEvaluator;
        $lines = [
            ['product_id' => 1, 'category_id' => null, 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000],
        ];

        // Mismatched customer
        $resultWrong = $evaluator->evaluate($promo, 50000, $lines, Carbon::now(), $custOther->id);
        $this->assertFalse($resultWrong['eligible']);
        $this->assertSame('promo_customer_mismatch', $resultWrong['reason']);

        // Correct customer
        $resultMatch = $evaluator->evaluate($promo, 50000, $lines, Carbon::now(), $custTarget->id);
        $this->assertTrue($resultMatch['eligible']);
        $this->assertSame(10000, $resultMatch['discount']);
    }

    public function test_06_pos_preview_promo_respects_customer(): void
    {
        [$business, $owner] = $this->setupTenant();
        $product = Product::factory()->create(['business_id' => $business->id, 'price' => 100000, 'status' => 'active']);
        $customer = Customer::factory()->create(['business_id' => $business->id]);

        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Diskon Pelanggan 15%',
            'discount_type' => PromoDiscountType::Percent->value,
            'discount_value' => 1500, // 15%
            'scope' => PromoScope::Customer->value,
            'min_purchase' => 50000,
            'status' => PromoStatus::Active->value,
        ]);

        PromoTarget::create([
            'business_id' => $business->id,
            'promo_id' => $promo->id,
            'target_type' => PromoScope::Customer->value,
            'target_id' => $customer->id,
        ]);

        // 1. Preview without customer
        $resNoCust = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.promo-preview'), [
                'promo_id' => $promo->id,
                'customer_id' => null,
                'subtotal' => 100000,
                'lines' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'line_total' => 100000],
                ],
            ]);

        $resNoCust->assertOk();
        $this->assertFalse($resNoCust->json('eligible'));
        $this->assertSame('promo_customer_required', $resNoCust->json('reason'));

        // 2. Preview with valid customer
        $resWithCust = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.promo-preview'), [
                'promo_id' => $promo->id,
                'customer_id' => $customer->id,
                'subtotal' => 100000,
                'lines' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'line_total' => 100000],
                ],
            ]);

        $resWithCust->assertOk();
        $this->assertTrue($resWithCust->json('eligible'));
        $this->assertSame(15000, $resWithCust->json('discount'));
    }

    public function test_07_promo_works_across_multiple_outlets_of_the_same_store(): void
    {
        [$business, $owner, $outlet1] = $this->setupTenant();

        // Second outlet (cabang kedua) of the same business
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Sudirman']);

        $product = Product::factory()->create(['business_id' => $business->id, 'price' => 100000, 'status' => 'active', 'stock' => 50]);
        $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Pelanggan Setia']);

        // Promo applies to customer
        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Diskon Pelanggan Setia',
            'discount_type' => PromoDiscountType::Fixed->value,
            'discount_value' => 20000,
            'scope' => PromoScope::Customer->value,
            'min_purchase' => 50000,
            'status' => PromoStatus::Active->value,
        ]);

        PromoTarget::create([
            'business_id' => $business->id,
            'promo_id' => $promo->id,
            'target_type' => PromoScope::Customer->value,
            'target_id' => $customer->id,
        ]);

        // Open shift on Outlet 1
        $shift1 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet1->id,
            'user_id' => $owner->id,
            'shift_number' => 1,
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        // 1. Checkout in Outlet 1 -> Promo works!
        $res1 = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet1->id,
                'shift_id' => $shift1->id,
                'customer_id' => $customer->id,
                'promo_id' => $promo->id,
                'payment_method' => 'cash',
                'cash_received' => 80000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $res1->assertOk();
        $sale1 = Sale::query()->where('outlet_id', $outlet1->id)->firstOrFail();
        $this->assertSame(100000, (int) $sale1->subtotal);
        $this->assertSame(20000, (int) $sale1->discount_amount);
        $this->assertSame(80000, (int) $sale1->total_amount);

        // Open shift on Outlet 2 (Cabang lain dari toko yang sama)
        $shift2 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet2->id,
            'user_id' => $owner->id,
            'shift_number' => 2,
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        // 2. Checkout in Outlet 2 -> Promo ALSO works!
        $res2 = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet2->id,
                'shift_id' => $shift2->id,
                'customer_id' => $customer->id,
                'promo_id' => $promo->id,
                'payment_method' => 'cash',
                'cash_received' => 80000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $res2->assertOk();
        $sale2 = Sale::query()->where('outlet_id', $outlet2->id)->firstOrFail();
        $this->assertSame(100000, (int) $sale2->subtotal);
        $this->assertSame(20000, (int) $sale2->discount_amount);
        $this->assertSame(80000, (int) $sale2->total_amount);
    }

    public function test_08_promo_cannot_be_used_in_a_different_store_of_the_same_owner(): void
    {
        [$businessA, $owner] = $this->setupTenant();

        // Business B owned by the same owner
        $businessB = Business::factory()->create(['name' => 'Toko B']);
        $businessB->users()->attach($owner->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $businessB->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $productB = Product::factory()->create(['business_id' => $businessB->id, 'price' => 50000, 'status' => 'active']);
        $custB = Customer::factory()->create(['business_id' => $businessB->id]);

        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'user_id' => $owner->id,
            'shift_number' => 1,
            'status' => 'open',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        // Promo created in Business A
        $promoA = Promo::create([
            'business_id' => $businessA->id,
            'name' => 'Promo Toko A',
            'discount_type' => PromoDiscountType::Percent->value,
            'discount_value' => 5000, // 50%
            'scope' => PromoScope::Customer->value,
            'min_purchase' => 0,
            'status' => PromoStatus::Active->value,
        ]);

        // Checkout in Business B attempting to use Business A's promo is strictly rejected by the server
        $res = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outletB->id,
                'shift_id' => $shiftB->id,
                'customer_id' => $custB->id,
                'promo_id' => $promoA->id,
                'payment_method' => 'cash',
                'cash_received' => 50000,
                'items' => [
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]);

        // Promo from store A cannot be used in store B (strictly scoped to business)
        $res->assertStatus(422);
        $res->assertJsonValidationErrors('promo_id');
        $this->assertDatabaseMissing('sales', ['business_id' => $businessB->id]);
    }

    /**
     * @return array{0: Business, 1: User, 2: Outlet}
     */
    private function setupTenant(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Toko Utama']);
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Pusat']);

        return [$business, $user, $outlet];
    }
}
