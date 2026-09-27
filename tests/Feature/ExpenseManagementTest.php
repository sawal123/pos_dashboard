<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_record_expense(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $shift = $this->createShift($business, $outlet);

        $this->actingAs($user)->post(route('cash.expenses.store'), $this->expensePayload([
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli bahan baku',
            'category' => 'Dapur',
            'amount' => 73000,
            'notes' => 'Supplier pagi',
        ]))->assertRedirect(route('cash.index', ['tab' => 'expenses']));

        $this->assertDatabaseHas('expenses', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'description' => 'Beli bahan baku',
            'category' => 'Dapur',
            'amount' => 73000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Beli bahan baku');
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_expense'] === 73000
            && $summary['cash_out'] === 0);
    }

    public function test_expense_paid_from_cash_creates_linked_cash_ledger_once(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $key = (string) Str::uuid();
        $payload = $this->expensePayload([
            'outlet_id' => $outlet->id,
            'amount' => 41000,
            'description' => 'Beli plastik',
            'paid_from_cash' => '1',
            'idempotency_key' => $key,
        ]);

        $this->actingAs($user)->post(route('cash.expenses.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('cash.expenses.store'), $payload)->assertRedirect();

        $expense = Expense::where('business_id', $business->id)->firstOrFail();
        $ledger = CashLedger::where('business_id', $business->id)->firstOrFail();

        $this->assertSame(1, Expense::where('business_id', $business->id)->count());
        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
        $this->assertSame($expense->id, $ledger->expense_id);
        $this->assertSame('out', $ledger->type);
        $this->assertSame(41000, (int) $ledger->amount);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_expense'] === 41000
            && $summary['cash_out'] === 41000);
    }

    public function test_expense_not_paid_from_cash_does_not_create_cash_ledger(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();

        $this->actingAs($user)->post(route('cash.expenses.store'), $this->expensePayload([
            'outlet_id' => $outlet->id,
            'paid_from_cash' => null,
        ]))->assertRedirect();

        $this->assertSame(1, Expense::where('business_id', $business->id)->count());
        $this->assertSame(0, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_expense_amount_and_date_are_validated(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();

        $this->actingAs($user)
            ->from(route('cash.index', ['tab' => 'expenses']))
            ->post(route('cash.expenses.store'), $this->expensePayload([
                'outlet_id' => $outlet->id,
                'amount' => 0,
                'occurred_at' => 'invalid-date',
            ]))
            ->assertRedirect(route('cash.index', ['tab' => 'expenses']))
            ->assertSessionHasErrors(['amount', 'occurred_at'], null, 'expense');

        $this->assertSame(0, Expense::count());
    }

    public function test_expense_rejects_foreign_outlet_and_shift(): void
    {
        [$user, $businessA, $outletA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $shiftB = $this->createShift($businessB, $outletB);

        $this->actingAs($user)
            ->from(route('cash.index', ['tab' => 'expenses']))
            ->post(route('cash.expenses.store'), $this->expensePayload([
                'outlet_id' => $outletB->id,
                'shift_id' => $shiftB->id,
            ]))
            ->assertRedirect(route('cash.index', ['tab' => 'expenses']))
            ->assertSessionHasErrors(['outlet_id', 'shift_id'], null, 'expense');

        $this->actingAs($user)
            ->from(route('cash.index', ['tab' => 'expenses']))
            ->post(route('cash.expenses.store'), $this->expensePayload([
                'outlet_id' => $outletA->id,
                'shift_id' => $shiftB->id,
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertRedirect(route('cash.index', ['tab' => 'expenses']))
            ->assertSessionHasErrors(['shift_id'], null, 'expense');

        $this->assertSame(0, Expense::count());
    }

    public function test_member_and_cashier_cannot_record_expense(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$user, $business, $outlet] = $this->makeUserWithBusiness($role);

            $this->actingAs($user)
                ->post(route('cash.expenses.store'), $this->expensePayload(['outlet_id' => $outlet->id]))
                ->assertForbidden();
        }

        $this->assertSame(0, Expense::count());
    }

    public function test_void_expense_uses_status_and_reverses_linked_cash(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $this->actingAs($user)->post(route('cash.expenses.store'), $this->expensePayload([
            'outlet_id' => $outlet->id,
            'amount' => 32000,
            'paid_from_cash' => '1',
        ]))->assertRedirect();

        $expense = Expense::where('business_id', $business->id)->firstOrFail();

        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();
        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $business->id,
            'expense_id' => $expense->id,
            'type' => 'in',
            'amount' => 32000,
            'category' => 'expense_void',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_expense'] === 0
            && $summary['cash_in'] === 32000
            && $summary['cash_out'] === 32000);
    }

    public function test_dashboard_expenses_receive_sync_metadata(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();

        $this->actingAs($user)->post(route('cash.expenses.store'), $this->expensePayload([
            'outlet_id' => $outlet->id,
        ]))->assertRedirect();

        $expense = Expense::where('business_id', $business->id)->firstOrFail();

        $this->assertNotNull($expense->sync_id);
        $this->assertSame(1, (int) $expense->sync_version);
        $this->assertGreaterThan(0, (int) $expense->sync_sequence);
    }

    public function test_business_switching_controls_expense_mutation_tenant(): void
    {
        [$user, $businessA, $outletA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->from(route('cash.index', ['tab' => 'expenses']))
            ->post(route('cash.expenses.store'), $this->expensePayload([
                'outlet_id' => $outletA->id,
            ]))
            ->assertRedirect(route('cash.index', ['tab' => 'expenses']))
            ->assertSessionHasErrors(['outlet_id'], null, 'expense');

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->post(route('cash.expenses.store'), $this->expensePayload([
                'outlet_id' => $outletB->id,
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertRedirect();

        $this->assertSame(0, Expense::where('business_id', $businessA->id)->count());
        $this->assertSame(1, Expense::where('business_id', $businessB->id)->count());
    }

    /**
     * @return array{0: User, 1: Business, 2: Outlet}
     */
    private function makeUserWithBusiness(string $role = 'owner'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => $role]);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business, $outlet];
    }

    private function createShift(Business $business, Outlet $outlet): Shift
    {
        return Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-'.Str::upper(Str::random(6)),
            'opening_cash' => 0,
            'opened_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Biaya operasional dashboard',
            'category' => 'Operasional',
            'amount' => 25000,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'outlet_id' => null,
            'shift_id' => null,
            'notes' => 'Catatan pengeluaran',
            'paid_from_cash' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }
}
