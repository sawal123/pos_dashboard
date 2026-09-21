<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardTransactionsData
{
    private const PER_PAGE = 25;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $business, array $filters): array
    {
        if ($business === null) {
            return $this->emptyResult($filters);
        }

        $baseQuery = Sale::query()->where('business_id', $business->id);

        $filteredQuery = $this->applyFilters(clone $baseQuery, $filters);

        /** @var LengthAwarePaginator<int, Sale> $transactions */
        $transactions = $filteredQuery
            ->with(['outlet', 'customer', 'shift', 'items'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $mappedTransactions = $transactions->through(fn (Sale $sale) => $this->presentSale($sale));

        $metrics = $this->buildMetrics($filteredQuery);
        $filterOptions = $this->buildFilterOptions($business);

        return [
            'transactions' => $mappedTransactions,
            'metrics' => $metrics,
            'filterOptions' => $filterOptions,
            'currentFilters' => $this->sanitizeFilters($filters),
        ];
    }

    /**
     * Apply all server-side filters to the given query builder.
     *
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Search: transaction_number, customer.name, customer_snapshot
        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $search = $q;
            $query->where(function ($sub) use ($search): void {
                $sub->where('transaction_number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', '%'.$search.'%'))
                    ->orWhere('customer_snapshot->name', 'like', '%'.$search.'%');
            });
        }

        // Date filter
        $date = isset($filters['date']) ? (string) $filters['date'] : 'all';
        $this->applyDateFilter($query, $date, $filters);

        // Outlet filter — uses outlet_id (stable key)
        $outletId = isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
            ? (int) $filters['outlet_id']
            : null;
        if ($outletId !== null) {
            $query->where('outlet_id', $outletId);
        }

        // Payment method — filter on raw DB value
        $paymentMethod = isset($filters['payment_method']) && $filters['payment_method'] !== '' && $filters['payment_method'] !== 'all'
            ? (string) $filters['payment_method']
            : null;
        if ($paymentMethod !== null) {
            $query->where('payment_method', $paymentMethod);
        }

        // Payment status — raw DB values: paid / unpaid
        $paymentStatus = isset($filters['payment_status']) && $filters['payment_status'] !== '' && $filters['payment_status'] !== 'all'
            ? (string) $filters['payment_status']
            : null;
        if ($paymentStatus !== null) {
            $query->where('payment_status', $paymentStatus);
        }

        // Transaction status — raw DB value
        $status = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? (string) $filters['status']
            : null;
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Apply date range filter using sold_at.
     *
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, string $date, array $filters): void
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();

        match ($date) {
            'today' => $query->whereBetween('sold_at', [
                $today->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '7days' => $query->whereBetween('sold_at', [
                $today->copy()->subDays(6)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '30days' => $query->whereBetween('sold_at', [
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
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCustomDateFilter(Builder $query, array $filters, string $tz): void
    {
        $startRaw = isset($filters['start_date']) ? trim((string) $filters['start_date']) : '';
        $endRaw = isset($filters['end_date']) ? trim((string) $filters['end_date']) : '';

        if ($startRaw === '' && $endRaw === '') {
            return;
        }

        $startDate = $startRaw !== '' ? Carbon::createFromFormat('Y-m-d', $startRaw, $tz)?->startOfDay() : null;
        $endDate = $endRaw !== '' ? Carbon::createFromFormat('Y-m-d', $endRaw, $tz)?->endOfDay() : null;

        if ($startDate === null && $endDate === null) {
            return;
        }

        // Safe swap if start > end
        if ($startDate !== null && $endDate !== null && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        if ($startDate !== null) {
            $query->where('sold_at', '>=', $startDate->toDateTimeString());
        }
        if ($endDate !== null) {
            $query->where('sold_at', '<=', $endDate->toDateTimeString());
        }
    }

    /**
     * Build aggregated metrics from the filtered query.
     *
     * @param  Builder<Sale>  $query
     * @return array<string, mixed>
     */
    private function buildMetrics(Builder $query): array
    {
        $metricsQuery = clone $query;

        $totalTransactions = $metricsQuery->count();
        $totalSales = (int) (clone $query)->sum('total_amount');
        $averageTransaction = $totalTransactions > 0 ? (int) round($totalSales / $totalTransactions) : 0;

        // Top payment method by count (non-null only)
        $paymentCounts = (clone $query)
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as cnt')
            ->groupBy('payment_method')
            ->orderByDesc('cnt')
            ->limit(1)
            ->first();

        $topPaymentMethodRaw = $paymentCounts !== null ? (string) $paymentCounts->payment_method : null;
        $topPaymentMethod = $topPaymentMethodRaw !== null ? $this->presentPaymentMethod($topPaymentMethodRaw) : '-';
        $topCount = $paymentCounts !== null ? (int) $paymentCounts->getAttribute('cnt') : 0;
        $topPaymentPercentage = $totalTransactions > 0 ? (int) round(($topCount / $totalTransactions) * 100) : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_sales' => $totalSales,
            'average_transaction' => $averageTransaction,
            'top_payment_method' => $topPaymentMethod,
            'top_payment_percentage' => $topPaymentPercentage,
        ];
    }

    /**
     * Build filter options for the current business.
     *
     * @return array<string, mixed>
     */
    private function buildFilterOptions(Business $business): array
    {
        /** @var Collection<int, Outlet> $outlets */
        $outlets = Outlet::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Distinct payment methods from actual data
        $paymentMethods = Sale::query()
            ->where('business_id', $business->id)
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');

        // Distinct transaction statuses from actual data
        $statuses = Sale::query()
            ->where('business_id', $business->id)
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return [
            'outlets' => $outlets->map(fn (Outlet $o) => ['id' => $o->id, 'name' => $o->name])->values()->toArray(),
            'payment_methods' => $paymentMethods->toArray(),
            'statuses' => $statuses->toArray(),
            'payment_statuses' => [
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'unpaid', 'label' => 'Belum Lunas'],
            ],
        ];
    }

    /**
     * Map a Sale Eloquent model to the presentation array shape.
     *
     * @return array<string, mixed>
     */
    private function presentSale(Sale $sale): array
    {
        // Customer name: snapshot first, then relation, then null
        $customerName = null;
        if (is_array($sale->customer_snapshot) && isset($sale->customer_snapshot['name']) && $sale->customer_snapshot['name'] !== '') {
            $customerName = (string) $sale->customer_snapshot['name'];
        } elseif ($sale->customer?->name !== null) {
            $customerName = $sale->customer->name;
        }

        // Outlet name: business_snapshot outlet first, then relation, then '-'
        $outletName = '-';
        if (is_array($sale->business_snapshot) && isset($sale->business_snapshot['outlet']) && $sale->business_snapshot['outlet'] !== '') {
            $outletName = (string) $sale->business_snapshot['outlet'];
        } elseif ($sale->outlet?->name !== null) {
            $outletName = $sale->outlet->name;
        }

        // Shift
        $shiftName = $sale->shift !== null ? $sale->shift->shift_number : '-';

        // Date presentation
        $soldAtCarbon = Carbon::parse($sale->sold_at);
        $soldAt = $this->formatDisplayDate($soldAtCarbon);
        $soldAtRaw = $soldAtCarbon->format('Y-m-d H:i:s');

        $paidAt = $this->formatDisplayDate($sale->paid_at);
        $estimatedCompletedAt = $this->formatDisplayDate($sale->estimated_completed_at);

        // Payment method
        $paymentMethodRaw = $sale->payment_method;
        $paymentMethod = $this->presentPaymentMethod($paymentMethodRaw);

        // Payment status
        $paymentStatusRaw = $sale->payment_status;
        $paymentStatus = $this->presentPaymentStatus($paymentStatusRaw);

        // Transaction status
        $statusRaw = $sale->status;
        $status = $this->presentStatus($statusRaw);

        // Items — use historical SaleItem fields only
        $items = $sale->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'product_sku' => $item->product_sku,
            'unit_price' => $item->unit_price,
            'quantity' => $item->quantity, // decimal:3 — do not cast to int
            'unit' => $item->unit,
            'kind' => $item->kind,
            'pricing_unit' => $item->pricing_unit, // generic: kg, pcs, cup, etc.
            'line_total' => $item->line_total,
            'cost_snapshot' => $item->cost_snapshot,
            'line_cost' => $item->line_cost,
        ])->values()->toArray();

        return [
            'id' => $sale->id,
            'transaction_number' => $sale->transaction_number,

            'sold_at' => $soldAt,
            'sold_at_raw' => $soldAtRaw,

            'outlet_name' => $outletName,
            'shift_name' => $shiftName,
            'customer_name' => $customerName,

            'payment_method_raw' => $paymentMethodRaw,
            'payment_method' => $paymentMethod,

            'payment_status_raw' => $paymentStatusRaw,
            'payment_status' => $paymentStatus,

            'status_raw' => $statusRaw,
            'status' => $status,

            'subtotal' => $sale->subtotal,
            'discount_amount' => $sale->discount_amount,
            'tax_amount' => $sale->tax_amount,
            'total_amount' => $sale->total_amount,

            'paid_at' => $paidAt,
            'cash_received' => $sale->cash_received,
            'change_amount' => $sale->change_amount,

            'gross_profit' => $sale->gross_profit, // decimal:2 authoritative value

            'note' => $sale->note,

            'order_status' => $sale->order_status,
            'estimated_completed_at' => $estimatedCompletedAt,

            'items' => $items,
        ];
    }

    /**
     * Map raw payment_method to human-readable label.
     */
    private function presentPaymentMethod(?string $raw): string
    {
        if ($raw === null) {
            return 'Tidak Diketahui';
        }

        return match (strtolower($raw)) {
            'cash', 'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card', 'kartu' => 'Kartu',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Map raw payment_status to human-readable label.
     */
    private function presentPaymentStatus(string $raw): string
    {
        return match ($raw) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Lunas',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Map raw sale status to human-readable label.
     * Unknown values are rendered neutral — never default to "Dibatalkan".
     */
    private function presentStatus(string $raw): string
    {
        return match ($raw) {
            'completed' => 'Selesai',
            'cancelled', 'canceled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Sanitize filters for passing back to the view.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'q' => isset($filters['q']) ? (string) $filters['q'] : '',
            'date' => isset($filters['date']) ? (string) $filters['date'] : 'all',
            'start_date' => isset($filters['start_date']) ? (string) $filters['start_date'] : '',
            'end_date' => isset($filters['end_date']) ? (string) $filters['end_date'] : '',
            'outlet_id' => isset($filters['outlet_id']) ? (string) $filters['outlet_id'] : '',
            'payment_method' => isset($filters['payment_method']) ? (string) $filters['payment_method'] : '',
            'payment_status' => isset($filters['payment_status']) ? (string) $filters['payment_status'] : '',
            'status' => isset($filters['status']) ? (string) $filters['status'] : '',
        ];
    }

    /**
     * Return empty result when no business context is available.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function emptyResult(array $filters): array
    {
        // Empty paginator using a dummy query that returns nothing
        $empty = Sale::query()->whereRaw('1 = 0')->paginate(self::PER_PAGE)->withQueryString();

        return [
            'transactions' => $empty,
            'metrics' => [
                'total_transactions' => 0,
                'total_sales' => 0,
                'average_transaction' => 0,
                'top_payment_method' => '-',
                'top_payment_percentage' => 0,
            ],
            'filterOptions' => [
                'outlets' => [],
                'payment_methods' => [],
                'statuses' => [],
                'payment_statuses' => [
                    ['value' => 'paid', 'label' => 'Lunas'],
                    ['value' => 'unpaid', 'label' => 'Belum Lunas'],
                ],
            ],
            'currentFilters' => $this->sanitizeFilters($filters),
        ];
    }

    /**
     * Format a date for display with Indonesian locale.
     */
    private function formatDisplayDate(\DateTimeInterface|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = Carbon::parse($date);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y H:i');
    }
}
