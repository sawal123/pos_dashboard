<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_multi_line_receipt_creates_one_movement_per_line(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $first = $this->createProduct($business, ['stock' => 5]);
        $second = $this->createProduct($business, ['stock' => 2]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $first->id, 'quantity' => 10],
                    ['product_id' => $second->id, 'quantity' => 3],
                ],
                'reference_id' => 'PO-001',
                'note' => 'Penerimaan pagi',
            ])
            ->assertRedirect(route('stock.history'));

        $this->assertSame('15.000', (string) $first->fresh()->stock);
        $this->assertSame('5.000', (string) $second->fresh()->stock);
        $this->assertSame(2, StockMovement::query()->where('business_id', $business->id)->count());
        $this->assertSame('PO-001', StockMovement::query()->firstOrFail()->reference_id);
    }

    public function test_02_member_can_receive_stock(): void
    {
        [$business, $user] = $this->actingAsRole('member');
        $product = $this->createProduct($business, ['stock' => 1]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [['product_id' => $product->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('stock.history'));

        $this->assertSame('5.000', (string) $product->fresh()->stock);
    }

    public function test_03_cashier_is_forbidden(): void
    {
        [$business, $user] = $this->actingAsRole('cashier');
        $product = $this->createProduct($business);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertForbidden();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_04_invalid_line_rolls_back_whole_receipt(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $valid = $this->createProduct($business, ['stock' => 0]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $valid->id, 'quantity' => 10],
                    ['product_id' => $valid->id, 'quantity' => 0],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame('0.000', (string) $valid->fresh()->stock);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_05_unit_cost_updates_weighted_average_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 10, 'cost' => 1000]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 2000]],
            ])
            ->assertRedirect(route('stock.history'));

        // ((10 * 1000) + (10 * 2000)) / 20 = 1500
        $this->assertSame('1500.00', (string) $product->fresh()->cost);
    }

    public function test_06_receipt_without_unit_cost_keeps_existing_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 10, 'cost' => 1000]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [['product_id' => $product->id, 'quantity' => 5]],
            ]);

        $this->assertSame('1000.00', (string) $product->fresh()->cost);
    }

    public function test_07_foreign_tenant_product_returns_404(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $foreignBusiness = Business::factory()->create();
        $foreignProduct = $this->createProduct($foreignBusiness);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_08_receipt_idempotency_is_per_line(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $first = $this->createProduct($business, ['stock' => 0]);
        $second = $this->createProduct($business, ['stock' => 0]);

        $payload = [
            'lines' => [
                ['product_id' => $first->id, 'quantity' => 2],
                ['product_id' => $second->id, 'quantity' => 3],
            ],
            'idempotency_key' => 'receipt-once',
        ];

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), $payload);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), $payload);

        $this->assertSame('2.000', (string) $first->fresh()->stock);
        $this->assertSame('3.000', (string) $second->fresh()->stock);
        $this->assertSame(2, StockMovement::query()->where('business_id', $business->id)->count());
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(Business $business, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'business_id' => $business->id,
            'name' => 'Produk Uji',
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'kind' => 'product',
            'stock' => 10,
            'min_stock' => 2,
            'cost' => 1000,
        ], $attributes));
    }
}
