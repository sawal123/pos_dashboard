<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_record_cash_in_and_out(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $shift = $this->createShift($business, $outlet);

        $this->actingAs($user)->post(route('cash.ledger.store'), $this->cashPayload([
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'type' => 'in',
            'amount' => 125000,
            'category' => 'cash_in',
            'note' => 'Setoran modal kas',
            'idempotency_key' => (string) Str::uuid(),
        ]))->assertRedirect(route('cash.index', ['tab' => 'ledgers']));

        $this->actingAs($user)->post(route('cash.ledger.store'), $this->cashPayload([
            'outlet_id' => $outlet->id,
            'type' => 'out',
            'amount' => 25000,
            'category' => 'cash_out',
            'idempotency_key' => (string) Str::uuid(),
        ]))->assertRedirect(route('cash.index', ['tab' => 'ledgers']));

        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'type' => 'in',
            'amount' => 125000,
            'note' => 'Setoran modal kas',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Setoran modal kas');
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['cash_in'] === 125000
            && $summary['cash_out'] === 25000
            && $summary['net_cash_flow'] === 100000);
    }

    public function test_cash_amount_and_date_are_validated(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();

        $this->actingAs($user)
            ->from(route('cash.index'))
            ->post(route('cash.ledger.store'), $this->cashPayload([
                'outlet_id' => $outlet->id,
                'amount' => 0,
                'occurred_at' => 'not-a-date',
            ]))
            ->assertRedirect(route('cash.index'))
            ->assertSessionHasErrors(['amount', 'occurred_at'], null, 'cashLedger');

        $this->assertSame(0, CashLedger::count());
    }

    public function test_cash_rejects_foreign_outlet_and_shift(): void
    {
        [$user, $businessA, $outletA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $shiftB = $this->createShift($businessB, $outletB);

        $this->actingAs($user)
            ->from(route('cash.index'))
            ->post(route('cash.ledger.store'), $this->cashPayload([
                'outlet_id' => $outletB->id,
                'shift_id' => $shiftB->id,
            ]))
            ->assertRedirect(route('cash.index'))
            ->assertSessionHasErrors(['outlet_id', 'shift_id'], null, 'cashLedger');

        $this->actingAs($user)
            ->from(route('cash.index'))
            ->post(route('cash.ledger.store'), $this->cashPayload([
                'outlet_id' => $outletA->id,
                'shift_id' => $shiftB->id,
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertRedirect(route('cash.index'))
            ->assertSessionHasErrors(['shift_id'], null, 'cashLedger');

        $this->assertSame(0, CashLedger::count());
    }

    public function test_member_and_cashier_cannot_record_cash(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$user, $business, $outlet] = $this->makeUserWithBusiness($role);

            $this->actingAs($user)
                ->post(route('cash.ledger.store'), $this->cashPayload(['outlet_id' => $outlet->id]))
                ->assertForbidden();
        }

        $this->assertSame(0, CashLedger::count());
    }

    public function test_cash_double_submit_uses_idempotency_key(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $key = (string) Str::uuid();
        $payload = $this->cashPayload([
            'outlet_id' => $outlet->id,
            'amount' => 88000,
            'idempotency_key' => $key,
        ]);

        $this->actingAs($user)->post(route('cash.ledger.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('cash.ledger.store'), $payload)->assertRedirect();

        $this->assertSame(1, CashLedger::where('business_id', $business->id)->where('idempotency_key', $key)->count());
        $this->assertSame(88000, (int) CashLedger::where('business_id', $business->id)->sum('amount'));
    }

    public function test_cash_reversal_is_append_only_and_idempotent(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();
        $ledger = CashLedger::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'type' => 'in',
            'amount' => 50000,
            'category' => 'cash_in',
            'reference_id' => 'MANUAL-IN',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();
        $this->actingAs($user)->post(route('cash.ledger.reverse', $ledger->id))->assertRedirect();

        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $business->id,
            'type' => 'out',
            'amount' => 50000,
            'category' => 'reversal',
            'reference_id' => 'REV-CASH-'.$ledger->id,
        ]);
        $this->assertSame(2, CashLedger::where('business_id', $business->id)->count());
    }

    public function test_dashboard_cash_entries_receive_sync_metadata(): void
    {
        [$user, $business, $outlet] = $this->makeUserWithBusiness();

        $this->actingAs($user)->post(route('cash.ledger.store'), $this->cashPayload([
            'outlet_id' => $outlet->id,
            'idempotency_key' => (string) Str::uuid(),
        ]))->assertRedirect();

        $ledger = CashLedger::where('business_id', $business->id)->firstOrFail();

        $this->assertNotNull($ledger->sync_id);
        $this->assertSame(1, (int) $ledger->sync_version);
        $this->assertGreaterThan(0, (int) $ledger->sync_sequence);
    }

    public function test_forged_business_id_is_ignored_for_active_business_context(): void
    {
        [$user, $businessA, $outletA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->post(route('cash.ledger.store'), $this->cashPayload([
                'business_id' => $businessB->id,
                'outlet_id' => $outletA->id,
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertRedirect();

        $this->assertSame(1, CashLedger::where('business_id', $businessA->id)->count());
        $this->assertSame(0, CashLedger::where('business_id', $businessB->id)->count());
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
    private function cashPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'in',
            'amount' => 50000,
            'outlet_id' => null,
            'shift_id' => null,
            'category' => 'cash_in',
            'note' => 'Catatan kas dashboard',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }
}
