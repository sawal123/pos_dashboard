<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_owner_can_open_an_opname_session(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $this->createProduct($business, ['name' => 'Kopi']);
        $this->createProduct($business, ['name' => 'Susu']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.store'), ['note' => 'Opname mingguan'])
            ->assertRedirect();

        $opname = StockOpname::query()->where('business_id', $business->id)->firstOrFail();

        $this->assertSame('draft', $opname->status);
        $this->assertSame('Opname mingguan', $opname->note);
        $this->assertSame(2, $opname->items()->count());
    }

    public function test_02_member_can_save_counts_and_cashier_cannot(): void
    {
        [$business, $member] = $this->actingAsRole('member');
        $product = $this->createProduct($business, ['stock' => 10]);
        $opname = $this->openOpname($business, $member);
        $item = $opname->items()->where('product_id', $product->id)->firstOrFail();

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('stock.opname.counts.update', ['opnameId' => $opname->id]), [
                'items' => [['id' => $item->id, 'counted_stock' => 7]],
            ])
            ->assertRedirect(route('stock.opname.show', ['opnameId' => $opname->id]));

        $this->assertSame('7.000', (string) $item->fresh()->counted_stock);
        $this->assertSame('-3.000', (string) $item->fresh()->variance);

        [, $cashier] = $this->actingAsRole('cashier', $business);

        $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('stock.opname.counts.update', ['opnameId' => $opname->id]), [
                'items' => [['id' => $item->id, 'counted_stock' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_03_posting_writes_opname_movements_only_for_variances(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $changed = $this->createProduct($business, ['stock' => 10]);
        $unchanged = $this->createProduct($business, ['stock' => 4]);

        $opname = $this->openOpname($business, $user);

        $changedItem = $opname->items()->where('product_id', $changed->id)->firstOrFail();
        $unchangedItem = $opname->items()->where('product_id', $unchanged->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('stock.opname.counts.update', ['opnameId' => $opname->id]), [
                'items' => [
                    ['id' => $changedItem->id, 'counted_stock' => 6],
                    ['id' => $unchangedItem->id, 'counted_stock' => 4],
                ],
            ]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.post', ['opnameId' => $opname->id]))
            ->assertRedirect(route('stock.opname.show', ['opnameId' => $opname->id]));

        $this->assertSame('6.000', (string) $changed->fresh()->stock);
        $this->assertSame('4.000', (string) $unchanged->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->where('business_id', $business->id)->where('movement_type', 'opname')->count());
        $this->assertSame('posted', $opname->fresh()->status);
    }

    public function test_04_posting_twice_is_rejected(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $this->createProduct($business, ['stock' => 3]);
        $opname = $this->openOpname($business, $user);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.post', ['opnameId' => $opname->id]));

        $movementCount = StockMovement::query()->where('business_id', $business->id)->count();

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.post', ['opnameId' => $opname->id]))
            ->assertSessionHasErrors('status');

        $this->assertSame($movementCount, StockMovement::query()->where('business_id', $business->id)->count());
    }

    public function test_05_cancel_only_works_for_draft(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $this->createProduct($business);
        $opname = $this->openOpname($business, $user);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.cancel', ['opnameId' => $opname->id]));

        $this->assertSame('cancelled', $opname->fresh()->status);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.cancel', ['opnameId' => $opname->id]))
            ->assertSessionHasErrors('status');
    }

    public function test_06_foreign_tenant_opname_returns_404(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $foreignBusiness = Business::factory()->create();
        $foreignUser = User::factory()->create();
        $foreignBusiness->users()->attach($foreignUser->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $foreignBusiness->id]);
        $foreignOpname = $this->openOpname($foreignBusiness, $foreignUser);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.opname.show', ['opnameId' => $foreignOpname->id]))
            ->assertNotFound();
    }

    public function test_07_cashier_cannot_open_a_session(): void
    {
        [$business, $cashier] = $this->actingAsRole('cashier');

        $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.store'), [])
            ->assertForbidden();
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

    private function openOpname(Business $business, User $user): StockOpname
    {
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.opname.store'), []);

        return StockOpname::query()->where('business_id', $business->id)->latest('id')->firstOrFail();
    }
}
