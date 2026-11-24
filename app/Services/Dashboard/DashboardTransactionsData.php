<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $hasAnyTransactions = Sale::query()->where('business_id', $business->id)->exists();

        $baseQuery = Sale::query()->where('business_id', $business->id);

        $filteredQuery = $this->applyFilters(clone $baseQuery, $filters);

        $breakdownQuery = $this->applyFilters(clone $baseQuery, array_merge($filters, [
            'status' => 'all',
            'payment_status' => 'all',
        ]));

        $metrics = $this->buildMetrics(clone $filteredQuery, $filters, $breakdownQuery);

        /** @var LengthAwarePaginator<int, Sale> $transactions */
        $transactions = (clone $filteredQuery)
            ->with(['outlet', 'customer', 'shift', 'items'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $mappedTransactions = $transactions->through(fn (Sale $sale) => $this->presentSale($sale));

        $filterOptions = $this->buildFilterOptions($business);

        return [
            'transactions' => $mappedTransactions,
            'metrics' => $metrics,
            'filterOptions' => $filterOptions,
            'currentFilters' => $this->sanitizeFilters($filters),
            'hasAnyTransactions' => $hasAnyTransactions,
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

        // Date filter (defaults to 'today')
        $date = isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'today';
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

        // Payment status — raw DB values: paid (Lunas) / unpaid (Belum Lunas) / refund (Refund)
        $paymentStatus = isset($filters['payment_status']) && $filters['payment_status'] !== '' && $filters['payment_status'] !== 'all'
            ? strtolower((string) $filters['payment_status'])
            : null;
        if ($paymentStatus !== null) {
            if (in_array($paymentStatus, ['refund', 'refunded'], true)) {
                $query->whereIn('payment_status', ['refund', 'refunded', 'Refund']);
            } elseif (in_array($paymentStatus, ['paid', 'lunas'], true)) {
                $query->whereIn('payment_status', ['paid', 'Lunas']);
            } elseif (in_array($paymentStatus, ['unpaid', 'belum lunas'], true)) {
                $query->whereIn('payment_status', ['unpaid', 'Belum Lunas']);
            } else {
                $query->where('payment_status', $paymentStatus);
            }
        }

        // Status Pesanan filter — Batal / Proses / Siap
        $status = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? strtolower((string) $filters['status'])
            : null;
        if ($status !== null) {
            if (in_array($status, ['batal', 'cancelled', 'canceled', 'dibatalkan'], true)) {
                $query->where(function ($q) {
                    $q->whereIn('status', ['cancelled', 'canceled', 'batal', 'Dibatalkan'])
                      ->orWhereIn('order_status', ['Batal', 'batal', 'Dibatalkan']);
                });
            } elseif (in_array($status, ['proses', 'processing', 'diproses', 'masuk'], true)) {
                $query->where(function ($q) {
                    $q->whereIn('status', ['processing', 'proses', 'Proses'])
                      ->orWhereIn('order_status', ['Proses', 'Diproses', 'Masuk', 'proses']);
                });
            } elseif (in_array($status, ['siap', 'completed', 'ready', 'selesai'], true)) {
                $query->where(function ($q) {
                    $q->whereIn('status', ['completed', 'siap', 'ready', 'selesai', 'Siap'])
                      ->orWhereIn('order_status', ['Siap', 'Siap Diambil', 'Selesai', 'siap', 'completed']);
                });
            } else {
                $query->where(function ($q) use ($status) {
                    $q->where('status', $status)->orWhere('order_status', $status);
                });
            }
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
        $tz = config('app.timezone', 'Asia/Jakarta');
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
            $query->where('sold_at', '>=', $startDate->toDateTimeString());
        }
        if ($endDate !== null) {
            $query->where('sold_at', '<=', $endDate->toDateTimeString());
        }
    }

    /**
     * Build aggregated metrics from the filtered query.
     * Cancelled and refunded transactions are excluded from revenue / successful metrics.
     *
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMetrics(Builder $query, array $filters = [], ?Builder $breakdownQuery = null): array
    {
        $statusFilter = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? strtolower((string) $filters['status'])
            : null;
        $isFilteringBatal = in_array($statusFilter, ['batal', 'cancelled', 'canceled', 'dibatalkan'], true);

        $payStatusFilter = isset($filters['payment_status']) && $filters['payment_status'] !== '' && $filters['payment_status'] !== 'all'
            ? strtolower((string) $filters['payment_status'])
            : null;
        $isFilteringRefund = in_array($payStatusFilter, ['refund', 'refunded'], true);

        // Valid sales query excludes cancelled and refunded transactions
        $validSalesQuery = (clone $query)
            ->whereNotIn(DB::raw('LOWER(COALESCE(status, ""))'), ['cancelled', 'canceled', 'batal', 'dibatalkan'])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['batal', 'cancelled', 'canceled', 'dibatalkan']);
            })
            ->whereNotIn(DB::raw('LOWER(COALESCE(payment_status, ""))'), ['refund', 'refunded']);

        // Status breakdown (Sukses / Proses / Batal & Refund) within the active date/outlet/search/method scope
        $scopeQuery = $breakdownQuery ?? $query;

        $notCancelledOrRefund = fn (Builder $q) => $q
            ->whereNotIn(DB::raw('LOWER(COALESCE(status, ""))'), ['cancelled', 'canceled', 'batal', 'dibatalkan'])
            ->where(function ($sub) {
                $sub->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['batal', 'cancelled', 'canceled', 'dibatalkan']);
            })
            ->whereNotIn(DB::raw('LOWER(COALESCE(payment_status, ""))'), ['refund', 'refunded']);

        $processingScope = $notCancelledOrRefund(clone $scopeQuery)
            ->where(function ($q) {
                $q->whereIn(DB::raw('LOWER(COALESCE(status, ""))'), ['processing', 'proses'])
                    ->orWhereIn(DB::raw('LOWER(COALESCE(order_status, ""))'), ['proses', 'diproses', 'masuk', 'processing']);
            });

        $successScope = $notCancelledOrRefund(clone $scopeQuery)
            ->whereNotIn(DB::raw('LOWER(COALESCE(status, ""))'), ['processing', 'proses'])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['proses', 'diproses', 'masuk', 'processing']);
            });

        $cancelledRefundScope = (clone $scopeQuery)
            ->where(function ($q) {
                $q->whereIn(DB::raw('LOWER(COALESCE(status, ""))'), ['cancelled', 'canceled', 'batal', 'dibatalkan'])
                    ->orWhereIn(DB::raw('LOWER(COALESCE(order_status, ""))'), ['batal', 'cancelled', 'canceled', 'dibatalkan'])
                    ->orWhereIn(DB::raw('LOWER(COALESCE(payment_status, ""))'), ['refund', 'refunded']);
            });

        $successCount = (clone $successScope)->count();
        $successSales = (int) (clone $successScope)->sum('total_amount');
        $processingCount = (clone $processingScope)->count();
        $processingAmount = (int) (clone $processingScope)->sum('total_amount');
        $cancelledRefundCount = (clone $cancelledRefundScope)->count();
        $cancelledRefundAmount = (int) (clone $cancelledRefundScope)->sum('total_amount');

        if ($isFilteringBatal || $isFilteringRefund) {
            // When user explicitly filters by Batal or Refund, count shows the filtered count, but sales revenue is 0
            $totalTransactions = (clone $query)->count();
            $totalSales = 0;
            $averageTransaction = 0;
            $topPaymentMethod = '-';
            $topPaymentPercentage = 0;
        } else {
            // Standard/default view: only non-cancelled valid sales count towards revenue and transaction counts
            $totalTransactions = (clone $validSalesQuery)->count();
            $totalSales = (int) (clone $validSalesQuery)->sum('total_amount');
            $averageTransaction = $totalTransactions > 0 ? (int) round($totalSales / $totalTransactions) : 0;

            $paymentCounts = (clone $validSalesQuery)
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
        }

        return [
            'total_transactions' => $totalTransactions,
            'total_sales' => $totalSales,
            'average_transaction' => $averageTransaction,
            'top_payment_method' => $topPaymentMethod,
            'top_payment_percentage' => $topPaymentPercentage,
            'success_count' => $successCount,
            'success_sales' => $successSales,
            'processing_count' => $processingCount,
            'processing_amount' => $processingAmount,
            'cancelled_refund_count' => $cancelledRefundCount,
            'cancelled_refund_amount' => $cancelledRefundAmount,
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

        // Distinct payment methods from actual data with presentation labels
        $rawPaymentMethods = Sale::query()
            ->where('business_id', $business->id)
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');

        $paymentMethods = $rawPaymentMethods->map(fn (string $pm) => [
            'value' => $pm,
            'label' => $this->presentPaymentMethod($pm),
        ])->values()->toArray();

        return [
            'outlets' => $outlets->map(fn (Outlet $o) => ['id' => $o->id, 'name' => $o->name])->values()->toArray(),
            'payment_methods' => $paymentMethods,
            'statuses' => ['siap', 'proses', 'batal'],
            'payment_statuses' => [
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'unpaid', 'label' => 'Belum Lunas'],
                ['value' => 'refund', 'label' => 'Refund'],
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
        $tz = config('app.timezone', 'Asia/Jakarta');
        $soldAtCarbon = Carbon::parse($sale->sold_at)->setTimezone($tz);
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

        // Transaction & Order status (Batal, Proses, Siap)
        $statusRaw = $sale->status;
        $orderStatusRaw = $sale->order_status;
        $status = $this->presentStatus($statusRaw, $orderStatusRaw);

        // Items — use historical SaleItem fields only
        $items = $sale->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'product_sku' => $item->product_sku,
            'variant_summary' => $item->variant_summary,
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
            'outlet_id' => $sale->outlet_id,
            'transaction_number' => $sale->transaction_number,

            'sold_at' => $soldAt,
            'sold_at_raw' => $soldAtRaw,

            'outlet_name' => $outletName,
            'shift_name' => $shiftName,
            'customer_name' => $customerName,
            'table_number' => $sale->table_number,

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

            'order_status' => $status,
            'order_status_raw' => $orderStatusRaw,
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
     * Map raw payment_status to human-readable label: Lunas, Belum Lunas, Refund.
     */
    private function presentPaymentStatus(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'paid', 'lunas' => 'Lunas',
            'unpaid', 'belum lunas' => 'Belum Lunas',
            'refund', 'refunded' => 'Refund',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Map raw sale/order status to human-readable label: Batal, Proses, Siap.
     */
    private function presentStatus(?string $raw, ?string $orderStatus = null): string
    {
        $rawStatus = strtolower(trim((string) $raw));
        $rawOrder = strtolower(trim((string) $orderStatus));

        if (in_array($rawStatus, ['cancelled', 'canceled', 'batal', 'dibatalkan'], true) || in_array($rawOrder, ['batal', 'cancelled', 'canceled', 'dibatalkan'], true)) {
            return 'Batal';
        }

        if (in_array($rawStatus, ['processing', 'proses'], true) || in_array($rawOrder, ['proses', 'diproses', 'masuk', 'processing'], true)) {
            return 'Proses';
        }

        if (in_array($rawStatus, ['completed', 'siap', 'ready', 'selesai'], true) || in_array($rawOrder, ['siap', 'siap diambil', 'selesai', 'completed', 'ready'], true)) {
            return 'Siap';
        }

        return 'Siap';
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
            'date' => isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'today',
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
        // Empty in-memory paginator without database queries
        $page = LengthAwarePaginator::resolveCurrentPage();
        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        return [
            'transactions' => $empty,
            'metrics' => [
                'total_transactions' => 0,
                'total_sales' => 0,
                'average_transaction' => 0,
                'top_payment_method' => '-',
                'top_payment_percentage' => 0,
                'success_count' => 0,
                'success_sales' => 0,
                'processing_count' => 0,
                'processing_amount' => 0,
                'cancelled_refund_count' => 0,
                'cancelled_refund_amount' => 0,
            ],
            'filterOptions' => [
                'outlets' => [],
                'payment_methods' => [],
                'statuses' => ['siap', 'proses', 'batal'],
                'payment_statuses' => [
                    ['value' => 'paid', 'label' => 'Lunas'],
                    ['value' => 'unpaid', 'label' => 'Belum Lunas'],
                    ['value' => 'refund', 'label' => 'Refund'],
                ],
            ],
            'currentFilters' => $this->sanitizeFilters($filters),
            'hasAnyTransactions' => false,
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

        $tz = config('app.timezone', 'Asia/Jakarta');
        $carbon = Carbon::parse($date)->setTimezone($tz);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y H:i');
    }
}
