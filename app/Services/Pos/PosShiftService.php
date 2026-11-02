<?php

namespace App\Services\Pos;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosShiftService
{
    public function __construct(
        protected PosNumberGenerator $numberGenerator
    ) {}

    /**
     * Get the active open shift for a specific outlet.
     */
    public function currentOpenShift(Business $business, Outlet $outlet): ?Shift
    {
        return Shift::query()
            ->where('business_id', $business->id)
            ->where('outlet_id', $outlet->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a new shift for an outlet.
     */
    public function openShift(
        Business $business,
        Outlet $outlet,
        int $openingCash,
        ?string $notes = null,
        ?User $user = null
    ): Shift {
        if ($openingCash < 0) {
            throw ValidationException::withMessages([
                'opening_cash' => 'Modal awal kasir tidak boleh bernilai negatif.',
            ]);
        }

        return DB::transaction(function () use ($business, $outlet, $openingCash, $notes): Shift {
            $existing = Shift::query()
                ->where('business_id', $business->id)
                ->where('outlet_id', $outlet->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Shift) {
                throw ValidationException::withMessages([
                    'shift' => "Shift #{$existing->shift_number} di outlet ini masih aktif dan belum ditutup.",
                ]);
            }

            $shiftNumber = $this->numberGenerator->generateShiftNumber($business, $outlet);

            $shift = Shift::create([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'shift_number' => $shiftNumber,
                'status' => 'open',
                'opening_cash' => $openingCash,
                'opened_at' => Carbon::now(),
                'notes' => $notes,
            ]);

            if ($openingCash > 0) {
                CashLedger::create([
                    'business_id' => $business->id,
                    'outlet_id' => $outlet->id,
                    'shift_id' => $shift->id,
                    'type' => 'in',
                    'amount' => $openingCash,
                    'category' => 'modal',
                    'note' => "Modal awal kasir shift {$shift->shift_number}",
                    'reference_id' => "MODAL-{$shift->shift_number}",
                    'idempotency_key' => "pos-shift-open:{$shift->shift_number}",
                    'occurred_at' => Carbon::now(),
                ]);
            }

            return $shift;
        });
    }

    /**
     * Close an active shift with closing cash verification.
     */
    public function closeShift(
        Business $business,
        Shift $shift,
        int $closingCash,
        ?string $notes = null
    ): Shift {
        if ($shift->business_id !== $business->id) {
            abort(404);
        }

        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift' => 'Shift ini sudah dalam keadaan ditutup.',
            ]);
        }

        if ($closingCash < 0) {
            throw ValidationException::withMessages([
                'closing_cash' => 'Uang kas penutupan tidak boleh bernilai negatif.',
            ]);
        }

        return DB::transaction(function () use ($shift, $closingCash, $notes): Shift {
            /** @var Shift $lockedShift */
            $lockedShift = Shift::query()
                ->where('id', $shift->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'shift' => 'Shift ini sudah dalam keadaan ditutup.',
                ]);
            }

            $combinedNotes = trim(($lockedShift->notes ? $lockedShift->notes."\n" : '').($notes ?? ''));

            $lockedShift->closing_cash = $closingCash;
            $lockedShift->status = 'closed';
            $lockedShift->closed_at = Carbon::now();
            $lockedShift->notes = $combinedNotes !== '' ? $combinedNotes : null;
            $lockedShift->save();

            return $lockedShift;
        });
    }

    /**
     * Calculate financial summary for a shift.
     *
     * @return array<string, int>
     */
    public function shiftSummary(Business $business, Shift $shift): array
    {
        $openingCash = (int) $shift->opening_cash;

        $cashSales = (int) DB::table('sales')
            ->where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('total_amount');

        $nonCashSales = (int) DB::table('sales')
            ->where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->sum('total_amount');

        $totalSales = $cashSales + $nonCashSales;

        $cashExpenses = (int) DB::table('cash_ledger')
            ->where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('type', 'out')
            ->sum('amount');

        $expectedCashInDrawer = $openingCash + $cashSales - $cashExpenses;

        $transactionsCount = (int) DB::table('sales')
            ->where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->count();

        return [
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
            'total_sales' => $totalSales,
            'cash_expenses' => $cashExpenses,
            'expected_cash' => $expectedCashInDrawer,
            'transactions_count' => $transactionsCount,
        ];
    }
}
