<?php

namespace Tests\Feature;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoTarget;
use App\Services\Promo\PromoEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromoEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_percent_uses_basis_points(): void
    {
        $promo = $this->promo(['discount_type' => 'percent', 'discount_value' => 1000]);

        $result = $this->evaluate($promo, 100000);

        $this->assertTrue($result['eligible']);
        $this->assertSame(10000, $result['discount']);
    }

    public function test_02_percent_is_floored(): void
    {
        $promo = $this->promo(['discount_type' => 'percent', 'discount_value' => 1000]);

        $this->assertSame(9999, $this->evaluate($promo, 99999)['discount']);
    }

    public function test_03_max_discount_caps_percent(): void
    {
        $promo = $this->promo([
            'discount_type' => 'percent',
            'discount_value' => 5000,
            'max_discount' => 5000,
        ]);

        $this->assertSame(5000, $this->evaluate($promo, 100000)['discount']);
    }

    public function test_04_fixed_discount_never_exceeds_subtotal(): void
    {
        $promo = $this->promo(['discount_type' => 'fixed', 'discount_value' => 20000]);

        $this->assertSame(10000, $this->evaluate($promo, 10000)['discount']);
    }

    public function test_05_min_purchase_is_enforced(): void
    {
        $promo = $this->promo(['discount_type' => 'fixed', 'discount_value' => 1000, 'min_purchase' => 50000]);

        $result = $this->evaluate($promo, 10000);

        $this->assertFalse($result['eligible']);
        $this->assertSame('promo_min_purchase', $result['reason']);
    }

    public function test_06_schedule_window_is_enforced(): void
    {
        $promo = $this->promo([
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'starts_at' => now()->addDay(),
        ]);

        $result = $this->evaluate($promo, 10000);

        $this->assertFalse($result['eligible']);
        $this->assertSame('promo_not_started', $result['reason']);
    }

    public function test_07_weekday_and_time_window_are_enforced(): void
    {
        $promo = $this->promo([
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'weekdays' => [1],
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $result = $this->evaluate($promo, 10000, '2026-10-31 12:00:00');

        $this->assertFalse($result['eligible']);
        $this->assertContains($result['reason'], ['promo_outside_weekday', 'promo_outside_time']);
    }

    public function test_08_product_scope_requires_a_matching_line(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->create(['business_id' => $business->id]);
        $other = Product::factory()->create(['business_id' => $business->id]);

        $promo = $this->promo([
            'business_id' => $business->id,
            'scope' => PromoScope::Product->value,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
        ]);

        PromoTarget::create([
            'business_id' => $business->id,
            'promo_id' => $promo->id,
            'target_type' => 'product',
            'target_id' => $product->id,
        ]);

        $matching = $this->evaluate($promo, 10000, null, [
            ['product_id' => $product->id, 'line_total' => 10000, 'quantity' => 1],
        ]);

        $mismatch = $this->evaluate($promo, 10000, null, [
            ['product_id' => $other->id, 'line_total' => 10000, 'quantity' => 1],
        ]);

        $this->assertTrue($matching['eligible']);
        $this->assertFalse($mismatch['eligible']);
        $this->assertSame('promo_target_mismatch', $mismatch['reason']);
    }

    public function test_09_price_override_discounts_the_target_lines(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->create(['business_id' => $business->id]);

        $promo = $this->promo([
            'business_id' => $business->id,
            'scope' => PromoScope::Product->value,
            'discount_type' => PromoDiscountType::PriceOverride->value,
            'discount_value' => 8000,
        ]);

        PromoTarget::create([
            'business_id' => $business->id,
            'promo_id' => $promo->id,
            'target_type' => 'product',
            'target_id' => $product->id,
        ]);

        $result = $this->evaluate($promo, 20000, null, [
            ['product_id' => $product->id, 'line_total' => 20000, 'quantity' => 2],
        ]);

        // 20000 - (8000 * 2) = 4000
        $this->assertSame(4000, $result['discount']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promo(array $attributes = []): Promo
    {
        $business = Business::factory()->create();

        return Promo::create(array_merge([
            'business_id' => $business->id,
            'name' => 'Promo Uji',
            'discount_type' => PromoDiscountType::Fixed->value,
            'discount_value' => 1000,
            'scope' => PromoScope::All->value,
            'status' => PromoStatus::Active->value,
        ], $attributes));
    }

    /**
     * @param  list<array<string, mixed>>|null  $lines
     * @return array{eligible: bool, discount: int, reason: string|null}
     */
    private function evaluate(Promo $promo, int $subtotal, ?string $at = null, ?array $lines = null): array
    {
        $lines ??= [['product_id' => 1, 'line_total' => $subtotal, 'quantity' => 1]];

        return app(PromoEvaluator::class)->evaluate(
            $promo,
            $subtotal,
            $lines,
            Carbon::parse($at ?? '2026-10-31 12:00:00'),
        );
    }
}
