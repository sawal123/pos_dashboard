<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseShiftFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // 1. SHIFT FOUNDATION TESTS
    // ==========================================

    public function test_shift_can_be_created_for_valid_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'status' => 'open',
            'opening_cash' => 100000,
            'closing_cash' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => 'Pagi shift',
        ]);

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'opening_cash' => 100000,
            'status' => 'open',
        ]);
        $this->assertNotNull($shift->id);
    }

    public function test_shift_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertNotNull($shift->business);
        $this->assertSame($business->id, $shift->business->id);
    }

    public function test_shift_belongs_to_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertNotNull($shift->outlet);
        $this->assertSame($outlet->id, $shift->outlet->id);
    }

    public function test_business_can_have_multiple_shifts(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift1 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
        $shift2 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-002',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertCount(2, $business->shifts);
        $this->assertTrue($business->shifts->contains($shift1));
        $this->assertTrue($business->shifts->contains($shift2));
    }

    public function test_outlet_can_have_multiple_shifts(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift1 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-001',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
        $shift2 = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-002',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertCount(2, $outlet->shifts);
        $this->assertTrue($outlet->shifts->contains($shift1));
        $this->assertTrue($outlet->shifts->contains($shift2));
    }

    public function test_shifts_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $shiftA = Shift::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-A',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-B',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertCount(1, $businessA->shifts);
        $this->assertTrue($businessA->shifts->contains($shiftA));
        $this->assertFalse($businessA->shifts->contains($shiftB));

        $this->assertCount(1, $businessB->shifts);
        $this->assertTrue($businessB->shifts->contains($shiftB));
        $this->assertFalse($businessB->shifts->contains($shiftA));
    }

    public function test_shift_of_business_a_cannot_use_outlet_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Shift::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-CROSS',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
    }

    public function test_shift_number_must_be_unique_within_same_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DUP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DUP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
    }

    public function test_same_shift_number_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $shiftA = Shift::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-SHARED',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-SHARED',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->assertDatabaseHas('shifts', ['id' => $shiftA->id, 'business_id' => $businessA->id]);
        $this->assertDatabaseHas('shifts', ['id' => $shiftB->id, 'business_id' => $businessB->id]);
    }

    public function test_default_status_of_shift_is_open(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DEF-STATUS',
            'opened_at' => now(),
        ]);

        $this->assertSame('open', $shift->status);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'open']);
    }

    public function test_closed_status_shift_can_be_stored(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-CLOSED',
            'status' => 'closed',
            'opening_cash' => 50000,
            'closing_cash' => 150000,
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
        ]);

        $this->assertSame('closed', $shift->status);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'closed']);
    }

    public function test_opening_cash_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-CASH',
            'opening_cash' => 250000,
            'opened_at' => now(),
        ]);

        $this->assertIsInt($shift->opening_cash);
        $this->assertSame(250000, $shift->opening_cash);
    }

    public function test_closing_cash_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-NOCLOSE',
            'closing_cash' => null,
            'opened_at' => now(),
        ]);

        $this->assertNull($shift->closing_cash);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'closing_cash' => null]);
    }

    public function test_closing_cash_is_stored_as_integer_when_provided(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-CLOSE-INT',
            'closing_cash' => 375000,
            'opened_at' => now(),
        ]);

        $this->assertIsInt($shift->closing_cash);
        $this->assertSame(375000, $shift->closing_cash);
    }

    public function test_opened_at_is_cast_to_datetime(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $openedAt = Carbon::parse('2026-08-21 08:00:00');

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-OPEN-DATE',
            'opened_at' => $openedAt,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $shift->opened_at);
        $this->assertSame('2026-08-21 08:00:00', $shift->opened_at->format('Y-m-d H:i:s'));
    }

    public function test_closed_at_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-NOCLOSEDAT',
            'opened_at' => now(),
            'closed_at' => null,
        ]);

        $this->assertNull($shift->closed_at);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'closed_at' => null]);
    }

    // ==========================================
    // 2. SALE ↔ SHIFT TESTS
    // ==========================================

    public function test_sale_without_shift_id_is_valid(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => null,
            'transaction_number' => 'TRX-NOSHIFT',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertNull($sale->shift_id);
        $this->assertNull($sale->shift);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'shift_id' => null]);
    }

    public function test_sale_can_use_shift_from_same_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-SAMESALE',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-WITHSHIFT',
            'subtotal' => 15000,
            'total_amount' => 15000,
            'sold_at' => now(),
        ]);

        $this->assertSame($shift->id, $sale->shift_id);
        $this->assertNotNull($sale->shift);
        $this->assertSame($shift->id, $sale->shift->id);
    }

    public function test_sale_relation_to_shift_works(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-REL',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-REL',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);

        $this->assertInstanceOf(Shift::class, $sale->shift);
        $this->assertSame('SHIFT-REL', $sale->shift->shift_number);
    }

    public function test_shift_can_have_multiple_sales(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-MULTISALES',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $sale1 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-S1',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $sale2 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-S2',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);

        $this->assertCount(2, $shift->sales);
        $this->assertTrue($shift->sales->contains($sale1));
        $this->assertTrue($shift->sales->contains($sale2));
    }

    public function test_sale_of_business_a_cannot_use_shift_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-BIZB',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftB->id,
            'transaction_number' => 'TRX-CROSS-BIZ-SHIFT',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
    }

    public function test_sale_of_outlet_a_cannot_use_shift_of_outlet_b_in_same_business(): void
    {
        $business = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-B']);

        $shiftB = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-OUTB',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftB->id,
            'transaction_number' => 'TRX-CROSS-OUTLET-SHIFT',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
    }

    // ==========================================
    // 3. EXPENSE FOUNDATION TESTS
    // ==========================================

    public function test_expense_can_be_created_for_valid_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Beli gallon air',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
            'notes' => 'Toko sebelah',
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Beli gallon air',
            'amount' => 25000,
            'status' => 'recorded',
        ]);
        $this->assertNotNull($expense->id);
    }

    public function test_expense_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Listrik',
            'amount' => 150000,
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($expense->business);
        $this->assertSame($business->id, $expense->business->id);
    }

    public function test_expense_belongs_to_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Kebersihan',
            'amount' => 20000,
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($expense->outlet);
        $this->assertSame($outlet->id, $expense->outlet->id);
    }

    public function test_business_can_have_multiple_expenses(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $exp1 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Expense 1',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);
        $exp2 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Expense 2',
            'amount' => 20000,
            'occurred_at' => now(),
        ]);

        $this->assertCount(2, $business->expenses);
        $this->assertTrue($business->expenses->contains($exp1));
        $this->assertTrue($business->expenses->contains($exp2));
    }

    public function test_outlet_can_have_multiple_expenses(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $exp1 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Expense 1',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);
        $exp2 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Expense 2',
            'amount' => 20000,
            'occurred_at' => now(),
        ]);

        $this->assertCount(2, $outlet->expenses);
        $this->assertTrue($outlet->expenses->contains($exp1));
        $this->assertTrue($outlet->expenses->contains($exp2));
    }

    public function test_expense_without_shift_is_valid(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => null,
            'description' => 'Sewa ruko',
            'amount' => 2000000,
            'occurred_at' => now(),
        ]);

        $this->assertNull($expense->shift_id);
        $this->assertNull($expense->shift);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'shift_id' => null]);
    }

    public function test_expense_can_use_shift_from_same_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-EXP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli plastik',
            'amount' => 15000,
            'occurred_at' => now(),
        ]);

        $this->assertSame($shift->id, $expense->shift_id);
        $this->assertNotNull($expense->shift);
        $this->assertSame($shift->id, $expense->shift->id);
    }

    public function test_expense_relation_to_shift_works(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-REL-EXP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli gas',
            'amount' => 22000,
            'occurred_at' => now(),
        ]);

        $this->assertInstanceOf(Shift::class, $expense->shift);
        $this->assertSame('SHIFT-REL-EXP', $expense->shift->shift_number);
    }

    public function test_shift_can_have_multiple_expenses(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-MULTI-EXP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $exp1 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli sedotan',
            'amount' => 12000,
            'occurred_at' => now(),
        ]);
        $exp2 = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli es batu',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);

        $this->assertCount(2, $shift->expenses);
        $this->assertTrue($shift->expenses->contains($exp1));
        $this->assertTrue($shift->expenses->contains($exp2));
    }

    public function test_expense_amount_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Maintenance AC',
            'amount' => 350000,
            'occurred_at' => now(),
        ]);

        $this->assertIsInt($expense->amount);
        $this->assertSame(350000, $expense->amount);
    }

    public function test_default_status_of_expense_is_recorded(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Default status test',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);

        $this->assertSame('recorded', $expense->status);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'status' => 'recorded']);
    }

    public function test_void_status_expense_can_be_stored(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Voided expense',
            'amount' => 10000,
            'status' => 'void',
            'occurred_at' => now(),
        ]);

        $this->assertSame('void', $expense->status);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'status' => 'void']);
    }

    public function test_expense_notes_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'No notes',
            'amount' => 10000,
            'notes' => null,
            'occurred_at' => now(),
        ]);

        $this->assertNull($expense->notes);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'notes' => null]);
    }

    public function test_occurred_at_is_cast_to_datetime(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $occurredAt = Carbon::parse('2026-08-21 12:30:00');

        $expense = Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Cast test',
            'amount' => 10000,
            'occurred_at' => $occurredAt,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $expense->occurred_at);
        $this->assertSame('2026-08-21 12:30:00', $expense->occurred_at->format('Y-m-d H:i:s'));
    }

    // ==========================================
    // 4. EXPENSE TENANT SAFETY TESTS
    // ==========================================

    public function test_expense_of_business_a_cannot_use_outlet_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Expense::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletB->id,
            'description' => 'Cross biz outlet expense',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);
    }

    public function test_expense_of_business_a_cannot_use_shift_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-EXP-BIZB',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Expense::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftB->id,
            'description' => 'Cross biz shift expense',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);
    }

    public function test_expense_of_outlet_a_cannot_use_shift_of_outlet_b_in_same_business(): void
    {
        $business = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-EA']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-EB']);

        $shiftB = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-OUTB-EXP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftB->id,
            'description' => 'Cross outlet shift expense',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);
    }

    // ==========================================
    // 5. DELETE SAFETY TESTS
    // ==========================================

    public function test_deleting_shift_referenced_by_sale_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DEL-SALE',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-DEL-SHIFT',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $shift->delete();
    }

    public function test_deleting_shift_referenced_by_expense_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DEL-EXP',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Direct delete exp check',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $shift->delete();
    }

    public function test_deleting_outlet_with_shift_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DEL-OUT',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $outlet->delete();
    }

    public function test_deleting_outlet_with_expense_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Outlet del exp check',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $outlet->delete();
    }

    public function test_deleting_business_a_cleans_all_tenant_entities(): void
    {
        $businessA = Business::factory()->create();
        $categoryA = Category::factory()->create(['business_id' => $businessA->id]);
        $productA = Product::factory()->create(['business_id' => $businessA->id, 'category_id' => $categoryA->id]);
        $customerA = Customer::factory()->create(['business_id' => $businessA->id]);
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $shiftA = Shift::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-ALL-A',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'customer_id' => $customerA->id,
            'shift_id' => $shiftA->id,
            'transaction_number' => 'TRX-ALL-A',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $itemA = SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleA->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_sku' => $productA->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $expenseA = Expense::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftA->id,
            'description' => 'Biaya A',
            'amount' => 15000,
            'occurred_at' => now(),
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $businessA->id]);
        $this->assertDatabaseMissing('categories', ['id' => $categoryA->id]);
        $this->assertDatabaseMissing('products', ['id' => $productA->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customerA->id]);
        $this->assertDatabaseMissing('outlets', ['id' => $outletA->id]);
        $this->assertDatabaseMissing('shifts', ['id' => $shiftA->id]);
        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $itemA->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expenseA->id]);
    }

    public function test_deleting_business_a_does_not_delete_data_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $shiftA = Shift::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-ISO-A',
            'opening_cash' => 50000,
            'opened_at' => now(),
        ]);
        $expenseA = Expense::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'shift_id' => $shiftA->id,
            'description' => 'Expense A',
            'amount' => 10000,
            'occurred_at' => now(),
        ]);

        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $shiftB = Shift::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-ISO-B',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);
        $expenseB = Expense::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'shift_id' => $shiftB->id,
            'description' => 'Expense B',
            'amount' => 20000,
            'occurred_at' => now(),
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $businessA->id]);
        $this->assertDatabaseMissing('outlets', ['id' => $outletA->id]);
        $this->assertDatabaseMissing('shifts', ['id' => $shiftA->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expenseA->id]);

        $this->assertDatabaseHas('businesses', ['id' => $businessB->id]);
        $this->assertDatabaseHas('outlets', ['id' => $outletB->id]);
        $this->assertDatabaseHas('shifts', ['id' => $shiftB->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expenseB->id]);
    }
}
