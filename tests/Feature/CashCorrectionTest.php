<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DASH-16 correction hardening.
 *
 * Covers the full reverse + void matrix: which rows may be corrected, which
 * must be routed to their originating mechanism, single-refund guarantees on
 * retry, and exactly-once behaviour under two simultaneous requests.
 */
class CashCorrectionTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1. reverseLedger — allowed manual corrections
    // ============================================================

    public function test_manual_cash_in_ledger_can_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, [
            'type' => 'in',
            'amount' => 75000,
            'category' => 'cash_in',
        ]);

        $this->actingAs($user)
            ->post(route('cash.ledger.reverse', $ledger->id))
            ->assertRedirect(route('cash.index', ['tab' => 'ledgers']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('reverses_ledger_id', $ledger->id)->count());

        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $business->id,
            'reverses_ledger_id' => $ledger->id,
            'type' => 'out',
            'amount' => 75000,
            'category' => CashLedger::CATEGORY_REVERSAL,
            'reference_id' => 'REV-CASH-'.$ledger->id,
        ]);
    }

    public function test_manual_cash_out_ledger_can_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, [
            'type' => 'out',
            'amount' => 31000,
            'category' => 'cash_out',
        ]);

        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();

        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $business->id,
            'reverses_ledger_id' => $ledger->id,
            'type' => 'in',
            'amount' => 31000,
            'category' => CashLedger::CATEGORY_REVERSAL,
        ]);
    }

    public function test_reversal_is_idempotent_on_retry(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, ['type' => 'in', 'amount' => 50000]);

        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();
        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();

        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('reverses_ledger_id', $ledger->id)->count());
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_reversal_survives_two_simultaneous_requests(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, ['type' => 'in', 'amount' => 64000]);

        $this->simulateConcurrentCashInsert(
            'cash-reversal:'.$ledger->id,
            'REV-CASH-'.$ledger->id,
            'REV-CASH-RACE-'.$ledger->id,
        );

        try {
            // A concurrent writer already appended the reversal; the request
            // must succeed without a 500 and without a second row.
            $this->actingAs($user)
                ->post(route('cash.ledger.reverse', $ledger->id))
                ->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS "simulate_cash_correction_race"');
        }

        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('reverses_ledger_id', $ledger->id)->count());
        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('reference_id', 'REV-CASH-RACE-'.$ledger->id)->count());
    }

    // ============================================================
    // 2. reverseLedger — routes non-manual cash to its origin
    // ============================================================

    public function test_sale_synced_ledger_cannot_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, [
            'type' => 'in',
            'amount' => 90000,
            'category' => 'sale',
            'sale_sync_id' => (string) Str::uuid(),
            'reference_id' => 'SALE-SETTLEMENT',
        ]);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        $response->assertSessionHasErrors('reversal');
        $this->assertStringContainsString('penjualan', (string) session('errors')->first('reversal'));

        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_pos_mobile_manual_cash_cannot_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->mobileLedger($business, $outlet);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        $response->assertSessionHasErrors('reversal');
        $this->assertStringContainsString('POS Mobile', (string) session('errors')->first('reversal'));

        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
        $this->assertSame(0, CashLedger::where('business_id', $business->id)
            ->where('category', CashLedger::CATEGORY_REVERSAL)->count());
    }

    public function test_pos_mobile_synced_manual_cash_cannot_be_reversed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Mobile POS',
            'identifier' => 'POS-MOB-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;
        $syncId = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-MOB-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'cash_ledger' => [[
                        'sync_id' => $syncId,
                        'base_sync_version' => null,
                        'type' => 'in',
                        'amount' => 55000,
                        'category' => 'cash_in',
                        'note' => 'Setoran kas manual POS Mobile',
                        'reference_id' => 'MOBILE-CASH-001',
                        'occurred_at' => now()->format('Y-m-d H:i:s'),
                    ]],
                ],
            ])->assertStatus(200);

        $ledger = CashLedger::where('business_id', $business->id)
            ->where('sync_id', $syncId)
            ->firstOrFail();

        // Sync rows are plain manual cash, yet carry no dashboard identity.
        $this->assertNull($ledger->sale_sync_id);
        $this->assertNull($ledger->expense_id);
        $this->assertNull($ledger->idempotency_key);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        $response->assertSessionHasErrors('reversal');
        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_row_cannot_forge_dashboard_reference_without_idempotency_key(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->mobileLedger($business, $outlet, [
            'reference_id' => 'DASH-CASH-ABCDEF123456',
        ]);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertSessionHasErrors('reversal');
        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_row_with_mismatched_dashboard_identity_is_rejected(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, [
            'reference_id' => 'DASH-CASH-000000000000',
        ]);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertSessionHasErrors('reversal');
        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_expense_linked_ledger_cannot_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expense($business, $outlet, 45000);
        $ledger = CashLedger::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'out',
            'amount' => 45000,
            'category' => CashLedger::CATEGORY_EXPENSE,
            'reference_id' => 'DASH-EXP-'.$expense->id,
            'expense_id' => $expense->id,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id));

        $response->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        $response->assertSessionHasErrors('reversal');
        $this->assertStringContainsString('void', (string) session('errors')->first('reversal'));

        $this->assertSame(1, CashLedger::where('business_id', $business->id)->count());
        $this->assertSame(0, CashLedger::where('business_id', $business->id)
            ->where('category', CashLedger::CATEGORY_REVERSAL)->count());
    }

    public function test_previous_reversal_cannot_be_reversed_again(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $original = $this->manualLedger($business, $outlet, ['type' => 'in', 'amount' => 12000]);

        $this->actingAs($user)->post(route('cash.ledger.reverse', $original->id))->assertRedirect();

        $reversal = CashLedger::where('business_id', $business->id)
            ->where('reverses_ledger_id', $original->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $reversal->id));

        $response->assertRedirect(route('cash.index', ['tab' => 'ledgers']));
        $response->assertSessionHasErrors('reversal');
        $this->assertStringContainsString('tidak dapat dibalik', (string) session('errors')->first('reversal'));

        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_expense_void_refund_cannot_be_reversed(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expense($business, $outlet, 20000);
        $payment = CashLedger::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'out',
            'amount' => 20000,
            'category' => CashLedger::CATEGORY_EXPENSE,
            'expense_id' => $expense->id,
            'occurred_at' => now(),
        ]);
        $refund = CashLedger::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'in',
            'amount' => 20000,
            'category' => CashLedger::CATEGORY_EXPENSE_VOID,
            'expense_id' => $expense->id,
            'reverses_ledger_id' => $payment->id,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('cash.ledger.reverse', $refund->id));

        $response->assertSessionHasErrors('reversal');
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    // ============================================================
    // 3. voidExpense — single refund guarantees
    // ============================================================

    public function test_void_expense_without_cash_link_only_marks_void(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expense($business, $outlet, 15000);

        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame(0, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_void_expense_refunds_linked_cash_once_and_is_retry_safe(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expensePaidFromCash($user, $outlet, 38000);

        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();
        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('category', CashLedger::CATEGORY_EXPENSE_VOID)->count());
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_void_expense_survives_two_simultaneous_requests(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expensePaidFromCash($user, $outlet, 27000);

        $this->simulateConcurrentCashInsert(
            'expense-void:'.$expense->id,
            'VOID-EXP-'.$expense->id,
            'VOID-EXP-RACE-'.$expense->id,
        );

        try {
            $this->actingAs($user)
                ->post(route('cash.expenses.void', $expense->id))
                ->assertRedirect(route('cash.index', ['tab' => 'expenses']));
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS "simulate_cash_correction_race"');
        }

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame(1, CashLedger::where('business_id', $business->id)
            ->where('category', CashLedger::CATEGORY_EXPENSE_VOID)->count());
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_void_expense_does_not_refund_again_when_cash_already_corrected(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expensePaidFromCash($user, $outlet, 33000);
        $payment = $expense->cashLedger;

        // Legacy correction: appended before the reversal link existed, so it
        // only shares the expense_id and carries a different idempotency key.
        CashLedger::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'in',
            'amount' => 33000,
            'category' => CashLedger::CATEGORY_REVERSAL,
            'reference_id' => 'REV-CASH-LEGACY-'.$payment->id,
            'expense_id' => $expense->id,
            'idempotency_key' => 'legacy-reversal:'.$payment->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame(0, CashLedger::where('business_id', $business->id)
            ->where('category', CashLedger::CATEGORY_EXPENSE_VOID)->count());
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_void_expense_keeps_deterministic_payment_relation(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $expense = $this->expensePaidFromCash($user, $outlet, 41000);
        $payment = $expense->cashLedger;

        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        $linked = $expense->fresh()->cashLedger;

        $this->assertNotNull($linked);
        $this->assertSame($payment->id, $linked->id);
        $this->assertSame('out', $linked->type);
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    // ============================================================
    // 4. Tenant isolation, RBAC, sync metadata
    // ============================================================

    public function test_reverse_and_void_are_tenant_scoped(): void
    {
        [$user, $businessA, $outletA] = $this->makeUserWithBusiness();

        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $foreignLedger = $this->manualLedger($businessB, $outletB, ['type' => 'in', 'amount' => 1000]);
        $foreignExpense = $this->expense($businessB, $outletB, 2000);

        $this->actingAs($user)
            ->post(route('cash.ledger.reverse', $foreignLedger->id))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('cash.expenses.void', $foreignExpense->id))
            ->assertNotFound();

        $this->assertSame(1, CashLedger::where('business_id', $businessB->id)->count());
        $this->assertSame('recorded', $foreignExpense->fresh()->status);
    }

    public function test_member_and_cashier_cannot_reverse_or_void(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$user, $business, $outlet] = $this->makeUserWithBusiness($role);
            $ledger = $this->manualLedger($business, $outlet, ['type' => 'in', 'amount' => 5000]);
            $expense = $this->expense($business, $outlet, 6000);

            $this->actingAs($user)
                ->post(route('cash.ledger.reverse', $ledger->id))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('cash.expenses.void', $expense->id))
                ->assertForbidden();
        }

        $this->assertSame(0, CashLedger::where('category', CashLedger::CATEGORY_REVERSAL)->count());
        $this->assertSame(0, Expense::where('status', 'void')->count());
    }

    public function test_correction_rows_receive_sync_metadata(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, ['type' => 'in', 'amount' => 8000]);

        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();
        $expense = $this->expensePaidFromCash($user, $outlet, 9000);
        $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertRedirect();

        foreach ([
            CashLedger::where('business_id', $business->id)->where('category', CashLedger::CATEGORY_REVERSAL)->firstOrFail(),
            CashLedger::where('business_id', $business->id)->where('category', CashLedger::CATEGORY_EXPENSE_VOID)->firstOrFail(),
        ] as $correction) {
            $this->assertNotNull($correction->sync_id);
            $this->assertSame(1, (int) $correction->sync_version);
            $this->assertGreaterThan(0, (int) $correction->sync_sequence);
        }
    }

    // ============================================================
    // Fixtures & helpers
    // ============================================================

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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manualLedger(Business $business, Outlet $outlet, array $overrides = []): CashLedger
    {
        $idempotencyKey = (string) Str::uuid();

        return CashLedger::create(array_merge([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'in',
            'amount' => 10000,
            'category' => 'cash_in',
            'reference_id' => CashLedger::manualReferenceId($idempotencyKey),
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ], $overrides));
    }

    /**
     * A manual cash entry as pushed by POS Mobile sync: no sale or expense
     * link, no dashboard idempotency key, device-local reference.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function mobileLedger(Business $business, Outlet $outlet, array $overrides = []): CashLedger
    {
        return CashLedger::create(array_merge([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'in',
            'amount' => 55000,
            'category' => 'cash_in',
            'reference_id' => 'MOBILE-CASH-'.Str::upper(Str::random(6)),
            'sync_id' => (string) Str::uuid(),
            'idempotency_key' => null,
            'occurred_at' => now(),
        ], $overrides));
    }

    private function expense(Business $business, Outlet $outlet, int $amount): Expense
    {
        return Expense::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Beban operasional',
            'category' => 'Operasional',
            'amount' => $amount,
            'status' => 'recorded',
            'occurred_at' => now(),
        ]);
    }

    private function expensePaidFromCash(User $user, Outlet $outlet, int $amount): Expense
    {
        $this->actingAs($user)->post(route('cash.expenses.store'), [
            'description' => 'Pengeluaran kas',
            'category' => 'Operasional',
            'amount' => $amount,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'outlet_id' => $outlet->id,
            'shift_id' => null,
            'notes' => null,
            'paid_from_cash' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        return Expense::where('outlet_id', $outlet->id)->latest('id')->firstOrFail();
    }

    /**
     * Simulate a concurrent request winning an INSERT race on `cash_ledger`.
     *
     * A SQLite BEFORE INSERT trigger inserts the "winner" correction row and
     * then aborts the outer insert with a UNIQUE violation. Laravel maps it to
     * UniqueConstraintViolationException, which the controller must absorb.
     */
    private function simulateConcurrentCashInsert(string $idempotencyKey, string $referenceId, string $winnerReferenceId): void
    {
        $pdo = DB::connection()->getPdo();
        $matchKey = $pdo->quote($idempotencyKey);
        $matchReference = $pdo->quote($referenceId);
        $winnerReference = $pdo->quote($winnerReferenceId);

        DB::unprepared(sprintf(<<<'SQL'
            CREATE TRIGGER "simulate_cash_correction_race"
            BEFORE INSERT ON "cash_ledger"
            WHEN NEW."idempotency_key" = %s AND NEW."reference_id" = %s
            BEGIN
                INSERT INTO "cash_ledger"
                    ("business_id", "outlet_id", "shift_id", "type", "amount", "category",
                     "note", "reference_id", "sale_sync_id", "expense_id", "reverses_ledger_id",
                     "idempotency_key", "occurred_at", "created_at", "updated_at",
                     "sync_version", "sync_sequence")
                VALUES
                    (NEW."business_id", NEW."outlet_id", NEW."shift_id", NEW."type", NEW."amount",
                     NEW."category", 'Race correction', %s, NULL, NEW."expense_id",
                     NEW."reverses_ledger_id", NEW."idempotency_key", NEW."occurred_at",
                     NEW."created_at", NEW."updated_at", 1, 0);
                SELECT RAISE(FAIL, 'UNIQUE constraint failed: cash_ledger.business_id, cash_ledger.idempotency_key');
            END
        SQL, $matchKey, $matchReference, $winnerReference));
    }
}
