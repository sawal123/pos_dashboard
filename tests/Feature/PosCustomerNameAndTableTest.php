<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Pos\PosCatalogData;
use App\Services\Pos\PosCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCustomerNameAndTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeform_customer_name_can_be_set_without_creating_customer_record(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['status' => 'active']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'price' => 25000,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $initialCustomerCount = Customer::where('business_id', $business->id)->count();

        // 1. Checkout with freeform customer name
        $checkoutService = app(PosCheckoutService::class);
        $clientSaleId = (string) Str::uuid();

        $sale = $checkoutService->checkout(
            business: $business,
            payload: [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'customer_id' => null,
                'customer_name' => 'Mas Dimas Walk-in',
                'table_number' => null,
                'payment_method' => 'cash',
                'cash_received' => 25000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            user: $owner
        );

        // Assert customer_id is null
        $this->assertNull($sale->customer_id);
        $this->assertNull($sale->customer);

        // Assert customer_snapshot contains the freeform name
        $this->assertIsArray($sale->customer_snapshot);
        $this->assertSame('Mas Dimas Walk-in', $sale->customer_snapshot['name']);

        // Assert no new record in customers table
        $this->assertSame($initialCustomerCount, Customer::where('business_id', $business->id)->count());

        // 2. Receipt endpoint displays freeform customer name
        $this->actingAs($owner);
        $response = $this->get(route('pos.receipt', ['saleId' => $sale->id]));
        $response->assertOk();
        $response->assertSee('Mas Dimas Walk-in');
    }

    public function test_table_management_setting_can_be_toggled_and_tables_managed(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['status' => 'active', 'table_management_enabled' => false]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $this->actingAs($owner);

        // Initially disabled
        $catalogData = app(PosCatalogData::class)->get($business, $outlet->id);
        $this->assertFalse($catalogData['tableManagementEnabled']);
        $this->assertEmpty($catalogData['diningTables']);

        // 1. Toggle on via settings
        $patchResponse = $this->patch(route('business-settings.tables.toggle'), [
            'table_management_enabled' => 1,
        ]);
        $patchResponse->assertRedirect(route('business-settings.edit'));
        $this->assertTrue($business->fresh()->table_management_enabled);

        // 2. Add tables
        $storeResponse1 = $this->post(route('business-settings.tables.store'), [
            'name' => 'Meja 01',
            'outlet_id' => $outlet->id,
            'capacity' => 4,
        ]);
        $storeResponse1->assertRedirect(route('business-settings.edit'));

        $storeResponse2 = $this->post(route('business-settings.tables.store'), [
            'name' => 'VIP Room',
            'outlet_id' => null, // all outlets
            'capacity' => 8,
        ]);
        $storeResponse2->assertRedirect(route('business-settings.edit'));

        $this->assertDatabaseHas('dining_tables', [
            'business_id' => $business->id,
            'name' => 'Meja 01',
            'outlet_id' => $outlet->id,
        ]);

        // 3. POS catalog data reflects enabled tables
        $catalogDataEnabled = app(PosCatalogData::class)->get($business->fresh(), $outlet->id);
        $this->assertTrue($catalogDataEnabled['tableManagementEnabled']);
        $this->assertCount(2, $catalogDataEnabled['diningTables']);
        $this->assertSame('Meja 01', $catalogDataEnabled['diningTables'][0]['name']);

        // 4. Delete table
        $tableToDelete = DiningTable::where('business_id', $business->id)->where('name', 'Meja 01')->firstOrFail();
        $deleteResponse = $this->delete(route('business-settings.tables.destroy', ['tableId' => $tableToDelete->id]));
        $deleteResponse->assertRedirect(route('business-settings.edit'));
        $this->assertDatabaseMissing('dining_tables', ['id' => $tableToDelete->id]);
    }

    public function test_sale_records_table_number_and_displays_in_receipt_and_transactions(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['status' => 'active', 'table_management_enabled' => true]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'price' => 30000,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $checkoutService = app(PosCheckoutService::class);
        $clientSaleId = (string) Str::uuid();

        // Checkout with table number and freeform customer name
        $sale = $checkoutService->checkout(
            business: $business,
            payload: [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'customer_id' => null,
                'customer_name' => 'Kak Sarah',
                'table_number' => 'Meja 07',
                'payment_method' => 'qris',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            user: $owner
        );

        $this->assertSame('Meja 07', $sale->table_number);
        $this->assertSame('Kak Sarah', $sale->customer_snapshot['name']);

        $this->actingAs($owner);

        // 1. Receipt shows table number
        $receiptResponse = $this->get(route('pos.receipt', ['saleId' => $sale->id]));
        $receiptResponse->assertOk();
        $receiptResponse->assertSee('No. Meja: Meja 07');
        $receiptResponse->assertSee('Pelanggan: Kak Sarah');

        // 2. Transactions list shows table number
        $transResponse = $this->get(route('transactions.index'));
        $transResponse->assertOk();
        $transResponse->assertSee('Meja 07');
        $transResponse->assertSee('Kak Sarah');
    }
}
