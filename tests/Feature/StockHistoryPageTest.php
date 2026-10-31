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

class StockHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_guests_are_redirected_to_login(): void
    {
        $this->get(route('stock.history'))->assertRedirect(route('login'));
    }

    public function test_02_unverified_users_are_redirected(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('stock.history'))->assertRedirect(route('verification.notice'));
    }

    public function test_03_verified_user_without_business_gets_empty_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('stock.history'));

        $response->assertOk();
        $response->assertSee('Belum ada riwayat stok');
    }

    public function test_04_owner_member_and_cashier_can_open_history(): void
    {
        foreach (['owner', 'member', 'cashier'] as $role) {
            [$business, $user] = $this->actingAsRole($role);

            $this->actingAs($user)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route('stock.history'))
                ->assertOk();
        }
    }

    public function test_05_history_lists_movements_of_active_business_only(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['name' => 'Kopi Arabica']);

        $this->createMovement($business, $product, ['note' => 'Penjualan pagi']);

        $otherBusiness = Business::factory()->create();
        $otherProduct = $this->createProduct($otherBusiness, ['name' => 'Produk Tenant Lain']);
        $this->createMovement($otherBusiness, $otherProduct, ['note' => 'Rahasia tenant lain']);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history'));

        $response->assertOk();
        $response->assertSee('Kopi Arabica');
        $response->assertSee('Penjualan pagi');
        $response->assertDontSee('Produk Tenant Lain');
        $response->assertDontSee('Rahasia tenant lain');
    }

    public function test_06_summary_splits_in_and_out(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);

        $this->createMovement($business, $product, ['quantity_change' => '10.000']);
        $this->createMovement($business, $product, ['quantity_change' => '-4.000']);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_in'] === 10.0
            && $summary['total_out'] === 4.0
            && $summary['net_change'] === 6.0
            && $summary['movement_count'] === 2);
    }

    public function test_07_type_filter_limits_rows(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);

        $this->createMovement($business, $product, ['movement_type' => 'sale', 'note' => 'Baris Penjualan']);
        $this->createMovement($business, $product, ['movement_type' => 'receipt', 'note' => 'Baris Penerimaan']);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history', ['type' => 'receipt']));

        $response->assertOk();
        $response->assertSee('Baris Penerimaan');
        $response->assertDontSee('Baris Penjualan');
    }

    public function test_08_history_is_paginated_twenty_five_per_page(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);

        for ($i = 0; $i < 26; $i++) {
            $this->createMovement($business, $product);
        }

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history'));

        $response->assertOk();
        $response->assertViewHas('items', fn ($items): bool => $items->perPage() === 25 && $items->total() === 26);
    }

    public function test_09_manual_mutation_button_only_for_stock_managers(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $this->createProduct($business);

        $ownerResponse = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history'));
        $ownerResponse->assertOk();
        $this->assertSame(true, $ownerResponse->viewData('canManageStock'));
        $ownerResponse->assertSee('id="openAdjustmentBtn"', false);

        [, $cashier] = $this->actingAsRole('cashier', $business);
        $cashierResponse = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.history'));
        $this->assertSame('cashier', $cashierResponse->viewData('dashboardBusinessRole'));
        $this->assertSame(false, $cashierResponse->viewData('canManageStock'));
        $cashierResponse->assertDontSee('id="openAdjustmentBtn"', false);
    }

    public function test_10_json_feed_returns_paginated_items(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);
        $this->createMovement($business, $product, ['note' => 'Feed item']);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->getJson(route('stock.history.data'));

        $response->assertOk();
        $response->assertJsonStructure(['items', 'summary', 'has_more']);
        $response->assertJsonPath('summary.movement_count', 1);
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role, ?Business $business = null): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business ??= Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);

        if (! Subscription::query()->where('business_id', $business->id)->exists()) {
            Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        }

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMovement(Business $business, Product $product, array $attributes = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_change' => '-1.000',
            'stock_before' => '10.000',
            'stock_after' => '9.000',
            'occurred_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }
}
