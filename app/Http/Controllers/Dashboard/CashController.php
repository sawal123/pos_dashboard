<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardCashData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(
        protected DashboardCashData $cashData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'tab',
            'q',
            'date',
            'start_date',
            'end_date',
            'outlet_id',
            'type',
            'category',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'tab' => ['nullable', 'string', Rule::in(['ledgers', 'expenses'])],
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7d', '30d', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(['in', 'out'])],
            'category' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->cashData->get($currentBusiness, $filters);
        $permissions = $request->attributes->get('dashboard_business_permissions', []);
        $data['canManageCash'] = is_array($permissions)
            && (in_array('*', $permissions, true) || in_array(BusinessPermission::CASH_MANAGE, $permissions, true));

        return view('cash.index', $data);
    }

    public function storeLedger(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validateWithBag('cashLedger', [
            'type' => ['required', 'string', Rule::in(['in', 'out'])],
            'amount' => ['required', 'integer', 'min:1'],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'shift_id' => [
                'nullable',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business->id)
                    ->where('outlet_id', (int) $request->input('outlet_id'))),
            ],
            'category' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ]);

        $ledger = DB::transaction(function () use ($business, $validated): CashLedger {
            $existing = CashLedger::where('business_id', $business->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CashLedger) {
                return $existing;
            }

            return CashLedger::create([
                'business_id' => $business->id,
                'outlet_id' => (int) $validated['outlet_id'],
                'shift_id' => $this->nullableInt($validated['shift_id'] ?? null),
                'type' => $validated['type'],
                'amount' => (int) $validated['amount'],
                'category' => trim((string) $validated['category']),
                'note' => $this->nullableString($validated['note'] ?? null),
                'reference_id' => $this->referenceId('DASH-CASH', (string) $validated['idempotency_key']),
                'sale_sync_id' => null,
                'expense_id' => null,
                'idempotency_key' => (string) $validated['idempotency_key'],
                'occurred_at' => Carbon::parse((string) $validated['occurred_at']),
            ]);
        });

        return redirect()
            ->route('cash.index', ['tab' => 'ledgers'])
            ->with('status', $ledger->wasRecentlyCreated ? 'Pergerakan kas berhasil dicatat.' : 'Pergerakan kas sudah pernah dicatat.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validateWithBag('expense', [
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'shift_id' => [
                'nullable',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business->id)
                    ->where('outlet_id', (int) $request->input('outlet_id'))),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_from_cash' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ]);

        $expense = DB::transaction(function () use ($business, $validated): Expense {
            $existing = Expense::where('business_id', $business->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Expense) {
                return $existing;
            }

            $expense = Expense::create([
                'business_id' => $business->id,
                'outlet_id' => (int) $validated['outlet_id'],
                'shift_id' => $this->nullableInt($validated['shift_id'] ?? null),
                'description' => trim((string) $validated['description']),
                'category' => trim((string) $validated['category']),
                'amount' => (int) $validated['amount'],
                'status' => 'recorded',
                'occurred_at' => Carbon::parse((string) $validated['occurred_at']),
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'idempotency_key' => (string) $validated['idempotency_key'],
            ]);

            if ((bool) ($validated['paid_from_cash'] ?? false)) {
                CashLedger::create([
                    'business_id' => $business->id,
                    'outlet_id' => (int) $validated['outlet_id'],
                    'shift_id' => $this->nullableInt($validated['shift_id'] ?? null),
                    'type' => 'out',
                    'amount' => (int) $validated['amount'],
                    'category' => 'expense',
                    'note' => trim((string) $validated['description']),
                    'reference_id' => $this->referenceId('DASH-EXP', (string) $validated['idempotency_key']),
                    'sale_sync_id' => null,
                    'expense_id' => $expense->id,
                    'idempotency_key' => 'expense-cash:'.$validated['idempotency_key'],
                    'occurred_at' => Carbon::parse((string) $validated['occurred_at']),
                ]);
            }

            return $expense;
        });

        return redirect()
            ->route('cash.index', ['tab' => 'expenses'])
            ->with('status', $expense->wasRecentlyCreated ? 'Pengeluaran berhasil dicatat.' : 'Pengeluaran sudah pernah dicatat.');
    }

    public function reverseLedger(Request $request, int $cashLedger): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $rejection = null;

        DB::transaction(function () use ($business, $cashLedger, &$rejection): void {
            $original = CashLedger::where('business_id', $business->id)
                ->where('id', $cashLedger)
                ->lockForUpdate()
                ->firstOrFail();

            // Only plain manual dashboard entries are reversible. Sale
            // settlements, expense payments, and earlier corrections must be
            // corrected through the mechanism that owns their origin.
            if (! $original->isManuallyReversible()) {
                $rejection = $this->reversalRejectionMessage($original);

                return;
            }

            // A row can only ever carry one correction. A retry or a
            // simultaneous request re-uses the deterministic link instead of
            // appending a second reversal.
            $alreadyReversed = CashLedger::where('business_id', $business->id)
                ->where('reverses_ledger_id', $original->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyReversed) {
                return;
            }

            $idempotencyKey = 'cash-reversal:'.$original->id;
            $existing = CashLedger::where('business_id', $business->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CashLedger) {
                return;
            }

            try {
                CashLedger::create([
                    'business_id' => $business->id,
                    'outlet_id' => $original->outlet_id,
                    'shift_id' => $original->shift_id,
                    'type' => $original->type === 'in' ? 'out' : 'in',
                    'amount' => $original->amount,
                    'category' => CashLedger::CATEGORY_REVERSAL,
                    'note' => 'Reversal untuk '.$this->referenceLabel($original),
                    'reference_id' => 'REV-CASH-'.$original->id,
                    'sale_sync_id' => null,
                    'expense_id' => null,
                    'reverses_ledger_id' => $original->id,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another request won the insert race; the reversal exists.
            }
        });

        if ($rejection !== null) {
            return redirect()
                ->route('cash.index', ['tab' => 'ledgers'])
                ->withErrors(['reversal' => $rejection]);
        }

        return redirect()
            ->route('cash.index', ['tab' => 'ledgers'])
            ->with('status', 'Reversal kas berhasil dicatat.');
    }

    public function voidExpense(Request $request, int $expense): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        DB::transaction(function () use ($business, $expense): void {
            $record = Expense::where('business_id', $business->id)
                ->where('id', $expense)
                ->with('cashLedger')
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status !== 'void') {
                $record->status = 'void';
                $record->save();
            }

            // `cashLedger` resolves the original payment row deterministically,
            // so a previously appended void refund can never be mistaken for it.
            $linkedLedger = $record->cashLedger;
            if (! $linkedLedger instanceof CashLedger || $linkedLedger->type !== 'out') {
                return;
            }

            // The cash for this expense may already be corrected (for example
            // by a legacy manual reversal appended before this guard existed).
            // Never return the same cash out twice.
            if ($this->linkedCashAlreadyCorrected($business, $record, $linkedLedger)) {
                return;
            }

            $idempotencyKey = 'expense-void:'.$record->id;
            $existing = CashLedger::where('business_id', $business->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CashLedger) {
                return;
            }

            try {
                CashLedger::create([
                    'business_id' => $business->id,
                    'outlet_id' => $record->outlet_id,
                    'shift_id' => $record->shift_id,
                    'type' => 'in',
                    'amount' => $record->amount,
                    'category' => CashLedger::CATEGORY_EXPENSE_VOID,
                    'note' => 'Void pengeluaran #'.$record->id,
                    'reference_id' => 'VOID-EXP-'.$record->id,
                    'sale_sync_id' => null,
                    'expense_id' => $record->id,
                    'reverses_ledger_id' => $linkedLedger->id,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another request won the insert race; the refund exists.
            }
        });

        return redirect()
            ->route('cash.index', ['tab' => 'expenses'])
            ->with('status', 'Pengeluaran berhasil dibatalkan.');
    }

    /**
     * Determine whether the cash linked to an expense was already corrected.
     *
     * Uses the explicit `reverses_ledger_id` link first, then falls back to
     * legacy correction rows that only carried the shared `expense_id`.
     */
    private function linkedCashAlreadyCorrected(Business $business, Expense $expense, CashLedger $payment): bool
    {
        return CashLedger::where('business_id', $business->id)
            ->where(function ($query) use ($expense, $payment): void {
                $query->where('reverses_ledger_id', $payment->id)
                    ->orWhere(function ($legacy) use ($expense, $payment): void {
                        $legacy->where('expense_id', $expense->id)
                            ->where('id', '!=', $payment->id)
                            ->whereIn('category', [
                                CashLedger::CATEGORY_REVERSAL,
                                CashLedger::CATEGORY_EXPENSE_VOID,
                            ]);
                    });
            })
            ->lockForUpdate()
            ->exists();
    }

    private function activeBusiness(Request $request): Business
    {
        $business = $request->attributes->get('dashboard_business');

        abort_unless($business instanceof Business, 403);

        return $business;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function referenceId(string $prefix, string $idempotencyKey): string
    {
        return $prefix.'-'.strtoupper(substr(str_replace('-', '', $idempotencyKey), 0, 12));
    }

    private function referenceLabel(CashLedger $ledger): string
    {
        return $ledger->reference_id !== null && $ledger->reference_id !== ''
            ? $ledger->reference_id
            : 'kas #'.$ledger->id;
    }

    /**
     * Explain which correction mechanism owns the original transaction so the
     * generic reversal flow is never used on cash it does not own.
     */
    private function reversalRejectionMessage(CashLedger $ledger): string
    {
        if ($ledger->sale_sync_id !== null) {
            return 'Pergerakan kas dari penjualan tidak dapat dibalik dari halaman kas. '
                .'Koreksi melalui transaksi penjualan terkait.';
        }

        if ($ledger->expense_id !== null) {
            return 'Pergerakan kas yang terhubung dengan pengeluaran harus dikoreksi '
                .'dengan membatalkan (void) pengeluaran terkait.';
        }

        return 'Koreksi kas sebelumnya tidak dapat dibalik kembali.';
    }
}
