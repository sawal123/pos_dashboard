<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DASH-16 — cash correction UI (B1).
 *
 * The reversal/void endpoints already existed and were server-tested; this
 * suite pins the owner-only dashboard affordance that B1 adds: which rows get
 * a correction action, which do not, and that a non-owner never receives the
 * action or the confirmation dialog. The server remains the final authority
 * (see CashCorrectionTest).
 */
class CashCorrectionUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_a_reverse_action_only_for_a_reversible_manual_row(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();

        $reversible = $this->manualLedger($business, $outlet, ['amount' => 71000]);
        $saleLinked = $this->manualLedger($business, $outlet, [
            'sale_sync_id' => (string) Str::uuid(),
            'amount' => 72000,
        ]);
        $linkedExpense = $this->expense($business, $outlet);
        $expenseLinked = $this->manualLedger($business, $outlet, [
            'expense_id' => $linkedExpense->id,
            'category' => 'expense',
            'amount' => 73000,
        ]);
        $mobileManual = $this->mobileLedger($business, $outlet, ['amount' => 74000]);
        $correction = $this->manualLedger($business, $outlet, [
            'category' => CashLedger::CATEGORY_REVERSAL,
            'reverses_ledger_id' => $mobileManual->id,
            'amount' => 75000,
        ]);

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'ledgers']));
        $response->assertOk();

        // Offered (desktop table + mobile card).
        $this->assertSame(2, substr_count($response->getContent(), route('cash.ledger.reverse', $reversible->id)));
        $response->assertSee('data-correction-kind="reversal"', false);

        // Never offered for sale-synced, expense-linked, POS-Mobile manual or
        // an existing correction row.
        foreach ([$saleLinked, $expenseLinked, $mobileManual, $correction] as $row) {
            $response->assertDontSee(route('cash.ledger.reverse', $row->id));
        }
    }

    public function test_owner_loses_the_reverse_action_once_a_row_is_already_reversed(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = $this->manualLedger($business, $outlet, ['amount' => 50000]);

        $this->actingAs($owner)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'ledgers']));
        $response->assertOk();

        $response->assertDontSee(route('cash.ledger.reverse', $ledger->id));
        // The reversed row is still visible, now flagged as a correction.
        $response->assertSee('REV-CASH-'.$ledger->id);
    }

    public function test_owner_sees_a_void_action_for_a_recorded_expense_only(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();

        $recorded = $this->expense($business, $outlet, ['status' => 'recorded', 'amount' => 31000]);
        $void = $this->expense($business, $outlet, ['status' => 'void', 'amount' => 32000]);

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), route('cash.expenses.void', $recorded->id)));
        $response->assertSee('data-correction-kind="void"', false);
        $response->assertDontSee(route('cash.expenses.void', $void->id));
    }

    public function test_member_and_cashier_never_receive_the_correction_actions_or_dialog(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$user, $business, $outlet] = $this->makeUserWithBusiness($role);
            $ledger = $this->manualLedger($business, $outlet);
            $expense = $this->expense($business, $outlet);

            $ledgers = $this->actingAs($user)->get(route('cash.index', ['tab' => 'ledgers']));
            $ledgers->assertOk();
            $ledgers->assertDontSee('data-correction-kind', false);
            $ledgers->assertDontSee(route('cash.ledger.reverse', $ledger->id));
            $ledgers->assertDontSee('id="cashCorrectionModal"', false);

            $expenses = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
            $expenses->assertOk();
            $expenses->assertDontSee('data-correction-kind', false);
            $expenses->assertDontSee(route('cash.expenses.void', $expense->id));
            $expenses->assertDontSee('id="cashCorrectionModal"', false);

            // And the server still refuses the mutation outright.
            $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertForbidden();
            $this->actingAs($user)->post(route('cash.expenses.void', $expense->id))->assertForbidden();
        }
    }

    public function test_owner_sees_the_confirmation_dialog_with_amount_type_and_consequence(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();
        $this->manualLedger($business, $outlet);

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'ledgers']));
        $response->assertOk();

        $response->assertSee('id="cashCorrectionModal"', false);
        $response->assertSee('id="cashCorrectionForm"', false);
        $response->assertSee('id="cashCorrectionAmount"', false);
        $response->assertSee('id="cashCorrectionConsequence"', false);
        $response->assertSee('data-correction-label', false);
    }

    public function test_the_dialog_exposes_the_keyboard_accessibility_hooks(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();
        $this->manualLedger($business, $outlet);

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'ledgers']));
        $response->assertOk();

        // Modal semantics + the element the client script focuses on open
        // (the focus trap and focus-restore logic key off these hooks).
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('data-correction-cancel', false);
        $response->assertSee('id="cashCorrectionSubmit"', false);
    }

    public function test_a_foreign_tenants_reversible_row_is_never_rendered(): void
    {
        [$owner, $business, $outlet] = $this->makeUserWithBusiness();
        [$otherOwner, $otherBusiness, $otherOutlet] = $this->makeUserWithBusiness();

        $mine = $this->manualLedger($business, $outlet);
        $foreign = $this->manualLedger($otherBusiness, $otherOutlet);

        $response = $this->actingAs($owner)->get(route('cash.index', ['tab' => 'ledgers']));
        $response->assertOk();

        $response->assertSee(route('cash.ledger.reverse', $mine->id));
        $response->assertDontSee(route('cash.ledger.reverse', $foreign->id));
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business, 2: Outlet}
     */
    private function makeUserWithBusiness(string $role = 'owner'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
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
     * A manual cash row pushed by POS Mobile: no dashboard idempotency key.
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
            'reference_id' => 'MOBILE-'.Str::random(8),
            'occurred_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function expense(Business $business, Outlet $outlet, array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'description' => 'Biaya Operasional',
            'category' => 'Operasional',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
        ], $overrides));
    }
}
