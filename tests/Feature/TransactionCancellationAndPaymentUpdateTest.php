<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardOutletsData;
use App\Services\Dashboard\DashboardOverviewData;
use App\Services\Dashboard\DashboardTransactionsData;
use App\Services\Pos\PosShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionCancellationAndPaymentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(array $attributes = []): Sale
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        $total = $attributes['total_amount'] ?? 50000;

        return Sale::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'gross_profit' => 20000.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => $total,
            'change_amount' => 0,
            'order_status' => 'Siap',
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }

    public function test_cancelling_a_paid_completed_transaction_reduces_revenue_across_dashboard_and_transactions(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        // Sale 1: Rp 30.000 (will remain completed)
        $sale1 = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'total_amount' => 30000,
            'payment_method' => 'QRIS',
            'status' => 'completed',
            'order_status' => 'Siap',
            'payment_status' => 'paid',
        ]);

        // Sale 2: Rp 20.000 (will be cancelled)
        $sale2 = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'total_amount' => 20000,
            'payment_method' => 'Tunai',
            'status' => 'completed',
            'order_status' => 'Siap',
            'payment_status' => 'paid',
        ]);

        // Verify initial state: Total Penjualan = 50.000, Total Transaksi = 2
        $txService = app(DashboardTransactionsData::class);
        $initialData = $txService->get($business, []);
        $this->assertEquals(50000, $initialData['metrics']['total_sales']);
        $this->assertEquals(2, $initialData['metrics']['total_transactions']);

        // Cancel Sale 2 via PATCH /transactions/{id}
        $response = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale2->id), [
                'payment_method' => 'Tunai',
                'payment_status' => 'paid', // Form may send paid, but backend auto-converts to refund on cancellation
                'status' => 'batal',
                'note' => 'Pelanggan membatalkan pesanan',
            ]);

        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('status');

        $sale2->refresh();
        $this->assertEquals('cancelled', $sale2->status);
        $this->assertEquals('Batal', $sale2->order_status);
        $this->assertEquals('refund', $sale2->payment_status);

        // Verify Transactions Page Metrics: Total Penjualan MUST decrease to 30.000, Total Transaksi to 1
        $updatedData = $txService->get($business, []);
        $this->assertEquals(30000, $updatedData['metrics']['total_sales']);
        $this->assertEquals(1, $updatedData['metrics']['total_transactions']);
        $this->assertEquals(30000, $updatedData['metrics']['average_transaction']);
        $this->assertEquals('QRIS', $updatedData['metrics']['top_payment_method']);

        // Verify Overview Dashboard: Today Sales MUST decrease to 30.000
        $overviewService = app(DashboardOverviewData::class);
        $overviewData = $overviewService->get($business, 'today');
        $this->assertEquals(30000, $overviewData['kpi']['today_sales']['raw']);
        $this->assertEquals(1, $overviewData['kpi']['today_transactions']['raw']);

        // Verify Outlets Page Data: Sales total MUST decrease to 30.000
        $outletsService = app(DashboardOutletsData::class);
        $outletsData = $outletsService->get($business, []);
        $primaryOutlet = $outletsData['outlets']->first();
        $this->assertEquals(30000, $primaryOutlet['sales_total']);
        $this->assertEquals(1, $primaryOutlet['sales_count']);

        // Verify Shift Summary: Total sales must decrease to 30.000 (cash sales became 0 since cash sale was cancelled)
        $shiftService = app(PosShiftService::class);
        $shiftSummary = $shiftService->shiftSummary($business, $shift);
        $this->assertEquals(0, $shiftSummary['cash_sales']);
        $this->assertEquals(30000, $shiftSummary['non_cash_sales']);
        $this->assertEquals(30000, $shiftSummary['total_sales']);
        $this->assertEquals(1, $shiftSummary['transactions_count']);
    }

    public function test_filtering_by_batal_shows_zero_revenue_and_cancelled_count(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'total_amount' => 45000,
            'status' => 'cancelled',
            'order_status' => 'Batal',
            'payment_status' => 'refund',
        ]);

        $txService = app(DashboardTransactionsData::class);
        $data = $txService->get($business, ['status' => 'batal']);

        $this->assertEquals(0, $data['metrics']['total_sales']);
        $this->assertEquals(1, $data['metrics']['total_transactions']);
        $this->assertEquals(0, $data['metrics']['average_transaction']);
    }

    public function test_payment_method_of_completed_order_can_be_updated_from_tunai_to_qris_and_vice_versa(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        // Completed and paid sale with Tunai
        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'total_amount' => 35000,
            'payment_method' => 'Tunai',
            'cash_received' => 50000,
            'change_amount' => 15000,
            'status' => 'completed',
            'order_status' => 'Siap',
            'payment_status' => 'paid',
        ]);

        // 1. Change from Tunai to QRIS on completed order
        $response = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'QRIS',
                'payment_status' => 'paid',
                'status' => 'siap',
                'note' => 'Ganti ke QRIS atas permintaan pelanggan',
            ]);

        $response->assertRedirect(route('transactions.index'));
        $sale->refresh();

        $this->assertEquals('QRIS', $sale->payment_method);
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals('Siap', $sale->order_status);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertNull($sale->cash_received);
        $this->assertNull($sale->change_amount);
        $this->assertEquals('Ganti ke QRIS atas permintaan pelanggan', $sale->note);

        // 2. Change back from QRIS to Tunai without providing cash_received (auto-defaults to exact total)
        $response2 = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'Tunai',
                'payment_status' => 'paid',
                'status' => 'siap',
            ]);

        $response2->assertRedirect(route('transactions.index'));
        $sale->refresh();

        $this->assertEquals('Tunai', $sale->payment_method);
        $this->assertEquals(35000, $sale->cash_received);
        $this->assertEquals(0, $sale->change_amount);

        // 3. Change to Debit
        $response3 = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'Debit',
                'payment_status' => 'paid',
                'status' => 'siap',
            ]);

        $response3->assertRedirect(route('transactions.index'));
        $sale->refresh();

        $this->assertEquals('Debit', $sale->payment_method);
        $this->assertNull($sale->cash_received);
        $this->assertNull($sale->change_amount);
    }
}
