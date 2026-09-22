<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DashboardCashData
{
    public const PER_PAGE = 25;

    /**
     * Fetch cash ledger and expense data scoped to the current business and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $activeTab = isset($filters['tab']) && $filters['tab'] === 'expenses' ? 'expenses' : 'ledgers';

        $currentFilters = [
            'tab' => $activeTab,
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'date' => isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'all',
            'start_date' => isset($filters['start_date']) ? (string) $filters['start_date'] : '',
            'end_date' => isset($filters['end_date']) ? (string) $filters['end_date'] : '',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
            'type' => isset($filters['type']) && $filters['type'] !== '' ? (string) $filters['type'] : 'all',
            'category' => isset($filters['category']) && $filters['category'] !== '' ? (string) $filters['category'] : 'all',
        ];

        // 1. Guard against null business (no tenant)
        if ($currentBusiness === null) {
            return [
                'activeTab' => $activeTab,
                'ledgers' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]),
                'expenses' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]),
                'summary' => [
                    'cash_in' => 0,
                    'cash_out' => 0,
                    'net_cash_flow' => 0,
                    'total_expense' => 0,
                ],
                'tabCounts' => [
                    'ledgers' => 0,
                    'expenses' => 0,
                ],
                'filterOptions' => [
                    'outlets' => [],
                    'categories' => [],
                ],
                'currentFilters' => $currentFilters,
                'hasAnyLedgers' => false,
                'hasAnyExpenses' => false,
            ];
        }

        $businessId = (int) $currentBusiness->id;

        // 2. Tab counts (unfiltered tenant totals)
        $totalLedgersCount = CashLedger::where('business_id', $businessId)->count();
        $totalExpensesCount = Expense::where('business_id', $businessId)
            ->where('status', '!=', 'void')
            ->count();

        $hasAnyLedgers = $totalLedgersCount > 0;
        $hasAnyExpenses = $totalExpensesCount > 0;

        // 3. Filter options
        $outlets = Outlet::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $categories = Expense::where('business_id', $businessId)
            ->where('status', '!=', 'void')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        // 4. Summary metrics using independent clones with COMMON filters only
        $summary = $this->buildSummary($businessId, $currentFilters);

        // 5. Active tab paginated dataset
        $ledgers = null;
        $expenses = null;

        if ($activeTab === 'ledgers') {
            $ledgerQuery = CashLedger::where('business_id', $businessId);
            $this->applyCommonFilters($ledgerQuery, $currentFilters);

            // Ledger specific filter: q
            if ($currentFilters['q'] !== '') {
                $q = $currentFilters['q'];
                $ledgerQuery->where(function (Builder $sub) use ($q) {
                    $sub->where('reference_id', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('note', 'like', "%{$q}%");
                });
            }

            // Ledger specific filter: type
            if ($currentFilters['type'] !== '' && $currentFilters['type'] !== 'all') {
                $ledgerQuery->where('type', $currentFilters['type']);
            }

            $paginatedLedgers = $ledgerQuery->with(['outlet', 'shift'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $paginatedLedgers->getCollection()->transform(function (CashLedger $ledger) {
                return $this->presentLedger($ledger);
            });

            $ledgers = $paginatedLedgers;
            $expenses = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } else {
            $expenseQuery = Expense::where('business_id', $businessId)
                ->where('status', '!=', 'void');
            $this->applyCommonFilters($expenseQuery, $currentFilters);

            // Expense specific filter: q
            if ($currentFilters['q'] !== '') {
                $q = $currentFilters['q'];
                $expenseQuery->where(function (Builder $sub) use ($q) {
                    $sub->where('description', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            }

            // Expense specific filter: category
            if ($currentFilters['category'] !== '' && $currentFilters['category'] !== 'all') {
                $expenseQuery->where('category', $currentFilters['category']);
            }

            $paginatedExpenses = $expenseQuery->with(['outlet', 'shift'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $paginatedExpenses->getCollection()->transform(function (Expense $expense) {
                return $this->presentExpense($expense);
            });

            $expenses = $paginatedExpenses;
            $ledgers = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return [
            'activeTab' => $activeTab,
            'ledgers' => $ledgers,
            'expenses' => $expenses,
            'summary' => $summary,
            'tabCounts' => [
                'ledgers' => $totalLedgersCount,
                'expenses' => $totalExpensesCount,
            ],
            'filterOptions' => [
                'outlets' => $outlets,
                'categories' => $categories,
            ],
            'currentFilters' => $currentFilters,
            'hasAnyLedgers' => $hasAnyLedgers,
            'hasAnyExpenses' => $hasAnyExpenses,
        ];
    }

    /**
     * Build summary metrics calculated from independent query builders.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function buildSummary(int $businessId, array $filters): array
    {
        // 1. Physical cash movements (CashLedger)
        $ledgerBase = CashLedger::where('business_id', $businessId);
        $this->applyCommonFilters($ledgerBase, $filters);

        $cashIn = (int) (clone $ledgerBase)->where('type', 'in')->sum('amount');
        $cashOut = (int) (clone $ledgerBase)->where('type', 'out')->sum('amount');
        $netCashFlow = $cashIn - $cashOut;

        // 2. Operational expenses (Expense) - strictly recorded status
        $expenseBase = Expense::where('business_id', $businessId)
            ->where('status', 'recorded');
        $this->applyCommonFilters($expenseBase, $filters);

        $totalExpense = (int) (clone $expenseBase)->sum('amount');

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_flow' => $netCashFlow,
            'total_expense' => $totalExpense,
        ];
    }

    /**
     * Apply common shared filters (date and outlet_id) to the query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        // Date filter on occurred_at
        $date = isset($filters['date']) ? (string) $filters['date'] : 'all';
        if ($date !== 'all') {
            $this->applyDateFilter($query, $date, $filters);
        }

        // Outlet ID filter
        if (isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all') {
            $query->where('outlet_id', (int) $filters['outlet_id']);
        }
    }

    /**
     * Apply date range filter using occurred_at.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, string $date, array $filters): void
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();

        match ($date) {
            'today' => $query->whereBetween('occurred_at', [
                $today->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '7d', '7days' => $query->whereBetween('occurred_at', [
                $today->copy()->subDays(6)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '30d', '30days' => $query->whereBetween('occurred_at', [
                $today->copy()->subDays(29)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            'custom' => $this->applyCustomDateFilter($query, $filters, $tz),
            default => null,
        };
    }

    /**
     * Apply custom date range filter with safe start/end swap.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCustomDateFilter(Builder $query, array $filters, string $tz): void
    {
        $startRaw = isset($filters['start_date']) ? trim((string) $filters['start_date']) : '';
        $endRaw = isset($filters['end_date']) ? trim((string) $filters['end_date']) : '';

        if ($startRaw === '' && $endRaw === '') {
            return;
        }

        $startDate = null;
        if ($startRaw !== '') {
            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $startRaw, $tz)?->startOfDay();
            } catch (\Throwable) {
                $startDate = null;
            }
        }

        $endDate = null;
        if ($endRaw !== '') {
            try {
                $endDate = Carbon::createFromFormat('Y-m-d', $endRaw, $tz)?->endOfDay();
            } catch (\Throwable) {
                $endDate = null;
            }
        }

        if ($startDate === null && $endDate === null) {
            return;
        }

        // Safe swap if start > end
        if ($startDate !== null && $endDate !== null && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        if ($startDate !== null) {
            $query->where('occurred_at', '>=', $startDate->toDateTimeString());
        }
        if ($endDate !== null) {
            $query->where('occurred_at', '<=', $endDate->toDateTimeString());
        }
    }

    /**
     * Format a CashLedger model into presentation array.
     *
     * @return array<string, mixed>
     */
    public function presentLedger(CashLedger $ledger): array
    {
        $typeRaw = (string) $ledger->type;
        $typeLabel = match ($typeRaw) {
            'in' => 'Kas Masuk',
            'out' => 'Kas Keluar',
            default => ucfirst(str_replace('_', ' ', $typeRaw)),
        };

        $categoryRaw = $ledger->category !== null ? (string) $ledger->category : null;
        $categoryLabel = match ($categoryRaw) {
            'sale' => 'Penjualan',
            'operational' => 'Operasional',
            'cash_in' => 'Setoran Kas',
            'cash_out' => 'Penarikan Kas',
            'other' => 'Lainnya',
            null => 'Tanpa Kategori',
            default => ucwords(str_replace('_', ' ', $categoryRaw)),
        };

        return [
            'id' => (int) $ledger->id,
            'type_raw' => $typeRaw,
            'type' => $typeLabel,
            'amount' => (int) $ledger->amount,
            'category_raw' => $categoryRaw,
            'category' => $categoryLabel,
            'note' => $ledger->note !== null ? (string) $ledger->note : null,
            'reference_id' => $ledger->reference_id !== null ? (string) $ledger->reference_id : null,
            'occurred_at_raw' => $ledger->occurred_at->format('Y-m-d H:i:s'),
            'occurred_at' => $ledger->occurred_at->translatedFormat('d M Y · H:i'),
            'outlet_id' => (int) $ledger->outlet_id,
            'outlet_name' => $ledger->outlet !== null ? $ledger->outlet->name : '-',
            'shift_number' => $ledger->shift !== null ? $ledger->shift->shift_number : null,
        ];
    }

    /**
     * Format an Expense model into presentation array.
     *
     * @return array<string, mixed>
     */
    public function presentExpense(Expense $expense): array
    {
        $statusRaw = (string) $expense->status;
        $statusLabel = match ($statusRaw) {
            'recorded' => 'Tercatat',
            default => ucwords(str_replace('_', ' ', $statusRaw)),
        };

        $categoryRaw = $expense->category !== null ? (string) $expense->category : null;
        $categoryLabel = $categoryRaw !== null && trim($categoryRaw) !== ''
            ? $categoryRaw
            : 'Tanpa Kategori';

        return [
            'id' => (int) $expense->id,
            'description' => (string) $expense->description,
            'category_raw' => $categoryRaw,
            'category' => $categoryLabel,
            'amount' => (int) $expense->amount,
            'status_raw' => $statusRaw,
            'status' => $statusLabel,
            'occurred_at_raw' => $expense->occurred_at->format('Y-m-d H:i:s'),
            'occurred_at' => $expense->occurred_at->translatedFormat('d M Y · H:i'),
            'notes' => $expense->notes !== null ? (string) $expense->notes : null,
            'outlet_id' => (int) $expense->outlet_id,
            'outlet_name' => $expense->outlet !== null ? $expense->outlet->name : '-',
            'shift_number' => $expense->shift !== null ? $expense->shift->shift_number : null,
        ];
    }
}
