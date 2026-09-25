<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\SaleItem;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read-only, tenant-scoped laundry order monitoring.
 *
 * A "laundry order" is a sale that carries a non-null `order_status`
 * (Masuk / Diproses / Siap Diambil / Selesai). Cafe/retail/grosir sales keep
 * `order_status = null` and are therefore never part of this dataset.
 *
 * Every query is scoped to the active business id; nothing here mutates state.
 */
class DashboardLaundryOrdersData
{
    public const PER_PAGE = 25;

    public const STATUS_INCOMING = 'Masuk';

    public const STATUS_PROCESSING = 'Diproses';

    public const STATUS_READY = 'Siap Diambil';

    public const STATUS_DONE = 'Selesai';

    /**
     * The canonical laundry lifecycle values, in lifecycle order.
     *
     * @var list<string>
     */
    private const CANONICAL_STATUSES = [
        self::STATUS_INCOMING,
        self::STATUS_PROCESSING,
        self::STATUS_READY,
        self::STATUS_DONE,
    ];

    /**
     * Transaction statuses that mean the sale was cancelled or voided.
     *
     * Audited from the project: `cancelled` / `canceled` (dashboard presentation
     * maps and feature tests) and `void` (SaleFoundationTest proves a sale can be
     * stored with status `void`). Sales are never tombstoned to `deleted` — the
     * sync contract rejects sale deletions — so that value is intentionally not
     * included.
     *
     * @var list<string>
     */
    private const CANCELLED_TRANSACTION_STATUSES = ['cancelled', 'canceled', 'void'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = $this->sanitizeFilters($filters);

        if ($currentBusiness === null) {
            return $this->emptyResult($currentFilters);
        }

        $businessId = (int) $currentBusiness->id;

        $hasAnyOrders = $this->baseQuery($businessId)->exists();

        // Summary reflects the whole active business (unfiltered), consistent
        // with the Shift/Customer/Outlet monitoring pages.
        $summary = $this->summary($businessId);

        $query = $this->baseQuery($businessId)
            ->with(['outlet', 'customer']);
        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, Sale> $paginator */
        $paginator = $query
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (Sale $sale): array => $this->presentOrder($sale));

        return [
            'orders' => $paginator,
            'summary' => $summary,
            'filterOptions' => $this->filterOptions($businessId),
            'currentFilters' => $currentFilters,
            'hasAnyOrders' => $hasAnyOrders,
        ];
    }

    /**
     * Full read-only detail for a single laundry order.
     *
     * A foreign business id, an unknown id, or an ordinary (non-laundry)
     * transaction all resolve to null → 404.
     *
     * @return array<string, mixed>
     */
    public function detail(Business $currentBusiness, int $saleId): array
    {
        /** @var Sale|null $sale */
        $sale = $this->baseQuery((int) $currentBusiness->id)
            ->whereKey($saleId)
            ->with(['outlet', 'customer', 'items'])
            ->first();

        if ($sale === null) {
            abort(404);
        }

        return $this->presentOrder($sale, includeItems: true);
    }

    /**
     * @return Builder<Sale>
     */
    private function baseQuery(int $businessId): Builder
    {
        return Sale::query()
            ->where('business_id', $businessId)
            ->whereNotNull('order_status');
    }

    /**
     * Whole-business laundry summary.
     *
     * `paid_revenue` is deliberately separated from the order counters and only
     * ever sums completed + paid transactions, so canceled/void orders can never
     * be presented as realised revenue.
     *
     * @return array<string, int>
     */
    private function summary(int $businessId): array
    {
        $base = $this->baseQuery($businessId);

        $statusCounts = (clone $base)
            ->selectRaw('order_status, COUNT(*) as aggregate_count')
            ->groupBy('order_status')
            ->pluck('aggregate_count', 'order_status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $paidRevenue = (int) (clone $base)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        return [
            'total_orders' => (int) (clone $base)->count(),
            'masuk' => $statusCounts[self::STATUS_INCOMING] ?? 0,
            'diproses' => $statusCounts[self::STATUS_PROCESSING] ?? 0,
            'siap_diambil' => $statusCounts[self::STATUS_READY] ?? 0,
            'selesai' => $statusCounts[self::STATUS_DONE] ?? 0,
            'overdue' => (int) $this->applyOverdue(clone $base)->count(),
            'paid_revenue' => $paidRevenue,
        ];
    }

    /**
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $term = (string) $filters['q'];
            $query->where(function (Builder $sub) use ($term): void {
                $sub->where('transaction_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($term): void {
                        $customerQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    })
                    ->orWhere('customer_snapshot->name', 'like', "%{$term}%")
                    ->orWhere('customer_snapshot->phone', 'like', "%{$term}%");
            });
        }

        if ($filters['order_status'] !== 'all') {
            $query->where('order_status', (string) $filters['order_status']);
        }

        if ($filters['payment_status'] !== 'all') {
            $query->where('payment_status', (string) $filters['payment_status']);
        }

        if ($filters['outlet_id'] !== '') {
            $query->where('outlet_id', (int) $filters['outlet_id']);
        }

        if ($filters['overdue'] === 'overdue') {
            $this->applyOverdue($query);
        } elseif ($filters['overdue'] === 'ontime') {
            $this->applyNotOverdue($query);
        }

        if ($filters['date'] !== 'all') {
            $this->applyDateFilter($query, (string) $filters['date'], $filters);
        }
    }

    /**
     * Overdue = an estimate exists, it is in the past, the laundry order is not
     * finished, and the underlying transaction was not cancelled or voided.
     * Orders without an estimate are never overdue, and an unpaid order is still
     * overdue while its laundry process remains active.
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function applyOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('estimated_completed_at')
            ->where('estimated_completed_at', '<', now()->toDateTimeString())
            ->whereNotIn('status', self::CANCELLED_TRANSACTION_STATUSES)
            ->where(function (Builder $sub): void {
                $sub->whereNull('order_status')
                    ->orWhere('order_status', '!=', self::STATUS_DONE);
            });
    }

    /**
     * The exact complement of {@see applyOverdue()}: orders without an estimate,
     * estimates not yet passed, finished orders, and cancelled/voided
     * transactions are all "not overdue".
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function applyNotOverdue(Builder $query): Builder
    {
        return $query->where(function (Builder $sub): void {
            $sub->whereNull('estimated_completed_at')
                ->orWhere('estimated_completed_at', '>=', now()->toDateTimeString())
                ->orWhere('order_status', self::STATUS_DONE)
                ->orWhereIn('status', self::CANCELLED_TRANSACTION_STATUSES);
        });
    }

    /**
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, string $date, array $filters): void
    {
        $tz = config('app.timezone', 'UTC');
        $today = Carbon::now($tz)->startOfDay();

        match ($date) {
            'today' => $query->whereBetween('sold_at', [
                $today->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '7d' => $query->whereBetween('sold_at', [
                $today->copy()->subDays(6)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '30d' => $query->whereBetween('sold_at', [
                $today->copy()->subDays(29)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            'custom' => $this->applyCustomDateFilter($query, $filters, $tz),
            default => null,
        };
    }

    /**
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
     * @return array<string, mixed>
     */
    private function filterOptions(int $businessId): array
    {
        $outlets = Outlet::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Outlet $outlet): array => [
                'id' => (int) $outlet->id,
                'name' => (string) $outlet->name,
            ])
            ->values()
            ->all();

        /** @var list<string> $distinctStatuses */
        $distinctStatuses = $this->baseQuery($businessId)
            ->whereNotNull('order_status')
            ->where('order_status', '!=', '')
            ->distinct()
            ->orderBy('order_status')
            ->pluck('order_status')
            ->map(fn ($status): string => (string) $status)
            ->values()
            ->all();

        $statusValues = array_values(array_unique([...self::CANONICAL_STATUSES, ...$distinctStatuses]));

        return [
            'outlets' => $outlets,
            'order_statuses' => array_map(
                fn (string $status): array => ['value' => $status, 'label' => $this->presentOrderStatus($status)],
                $statusValues,
            ),
            'payment_statuses' => [
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'unpaid', 'label' => 'Belum Lunas'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Sale $sale, bool $includeItems = false): array
    {
        $orderStatusRaw = $sale->order_status !== null ? (string) $sale->order_status : '';
        $paymentStatusRaw = (string) $sale->payment_status;

        $estimated = $sale->estimated_completed_at;
        $isCancelled = in_array(strtolower((string) $sale->status), self::CANCELLED_TRANSACTION_STATUSES, true);
        $isOverdue = $estimated !== null
            && $estimated->isPast()
            && $orderStatusRaw !== self::STATUS_DONE
            && ! $isCancelled;

        $order = [
            'id' => (int) $sale->id,
            'transaction_number' => (string) $sale->transaction_number,
            'customer_name' => $this->customerName($sale),
            'customer_phone' => $this->customerPhone($sale),
            'outlet_name' => $this->outletName($sale),
            'sold_at' => $this->formatDateTime($sale->sold_at),
            'sold_at_raw' => $sale->sold_at->format('Y-m-d H:i:s'),
            'estimated_completed_at' => $this->formatDateTime($estimated),
            'estimated_completed_at_raw' => $estimated?->format('Y-m-d H:i:s'),
            'order_status_raw' => $orderStatusRaw,
            'order_status' => $this->presentOrderStatus($orderStatusRaw),
            'order_status_category' => $this->orderStatusCategory($orderStatusRaw),
            'payment_status_raw' => $paymentStatusRaw,
            'payment_status' => $this->presentPaymentStatus($paymentStatusRaw),
            'payment_method_raw' => $sale->payment_method,
            'payment_method' => $this->presentPaymentMethod($sale->payment_method),
            'total_amount' => (int) $sale->total_amount,
            'is_overdue' => $isOverdue,
            'note' => $sale->note !== null ? (string) $sale->note : null,
        ];

        if ($includeItems) {
            $order['items'] = $sale->items
                ->map(fn ($item): array => $this->presentItem($item))
                ->values()
                ->all();
        }

        return $order;
    }

    /**
     * Historical sale-item snapshot only — never the current Product price.
     *
     * @return array<string, mixed>
     */
    private function presentItem(SaleItem $item): array
    {
        $quantity = (string) $item->quantity;

        return [
            'product_name' => (string) $item->product_name,
            'product_sku' => (string) $item->product_sku,
            'quantity_raw' => $quantity,
            'quantity' => (float) $quantity,
            'quantity_display' => $this->formatQuantity((float) $quantity),
            'unit' => $item->unit !== '' ? (string) $item->unit : 'pcs',
            'unit_price' => (int) $item->unit_price,
            'line_total' => (int) $item->line_total,
        ];
    }

    /**
     * Customer identity priority: stored snapshot → live relation → generic
     * label. Snapshots are only read as display data; rows are never grouped by
     * customer name, so two different customers sharing a name stay separate.
     */
    private function customerName(Sale $sale): string
    {
        $snapshotName = $this->snapshotValue($sale, 'name');
        if ($snapshotName !== null) {
            return $snapshotName;
        }

        if ($sale->customer?->name !== null && $sale->customer->name !== '') {
            return (string) $sale->customer->name;
        }

        return 'Pelanggan Umum';
    }

    private function customerPhone(Sale $sale): ?string
    {
        $snapshotPhone = $this->snapshotValue($sale, 'phone');
        if ($snapshotPhone !== null) {
            return $snapshotPhone;
        }

        $phone = $sale->customer?->phone;

        return $phone !== null && $phone !== '' ? (string) $phone : null;
    }

    private function snapshotValue(Sale $sale, string $key): ?string
    {
        $snapshot = $sale->customer_snapshot;

        if (! is_array($snapshot) || ! isset($snapshot[$key]) || ! is_scalar($snapshot[$key])) {
            return null;
        }

        $value = trim((string) $snapshot[$key]);

        return $value !== '' ? $value : null;
    }

    private function outletName(Sale $sale): string
    {
        $snapshot = $sale->business_snapshot;
        if (is_array($snapshot) && isset($snapshot['outlet']) && is_scalar($snapshot['outlet'])) {
            $snapshotOutlet = trim((string) $snapshot['outlet']);
            if ($snapshotOutlet !== '') {
                return $snapshotOutlet;
            }
        }

        return $sale->outlet?->name !== null && $sale->outlet->name !== '' ? (string) $sale->outlet->name : '-';
    }

    private function presentOrderStatus(string $status): string
    {
        if ($status === '') {
            return 'Tanpa Status';
        }

        return match ($status) {
            self::STATUS_INCOMING => 'Masuk',
            self::STATUS_PROCESSING => 'Diproses',
            self::STATUS_READY => 'Siap Diambil',
            self::STATUS_DONE => 'Selesai',
            default => ucwords(str_replace(['_', '-'], ' ', $status)),
        };
    }

    /**
     * @return 'incoming'|'processing'|'ready'|'done'|'other'
     */
    private function orderStatusCategory(string $status): string
    {
        return match ($status) {
            self::STATUS_INCOMING => 'incoming',
            self::STATUS_PROCESSING => 'processing',
            self::STATUS_READY => 'ready',
            self::STATUS_DONE => 'done',
            default => 'other',
        };
    }

    private function presentPaymentStatus(string $raw): string
    {
        return match ($raw) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Lunas',
            '' => 'Tidak Diketahui',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    private function presentPaymentMethod(?string $raw): string
    {
        if ($raw === null || $raw === '') {
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
     * Decimal-safe quantity presentation (e.g. 2.5 → "2,5").
     */
    private function formatQuantity(float $quantity): string
    {
        $formatted = number_format($quantity, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function formatDateTime(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = Carbon::instance($date);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y - H:i');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        $overdueRaw = isset($filters['overdue']) ? (string) $filters['overdue'] : 'all';

        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'order_status' => isset($filters['order_status']) && $filters['order_status'] !== '' && $filters['order_status'] !== 'all'
                ? (string) $filters['order_status']
                : 'all',
            'payment_status' => isset($filters['payment_status']) && in_array($filters['payment_status'], ['paid', 'unpaid'], true)
                ? (string) $filters['payment_status']
                : 'all',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
            'date' => isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'all',
            'start_date' => isset($filters['start_date']) ? (string) $filters['start_date'] : '',
            'end_date' => isset($filters['end_date']) ? (string) $filters['end_date'] : '',
            'overdue' => in_array($overdueRaw, ['overdue', 'ontime'], true) ? $overdueRaw : 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $currentFilters
     * @return array<string, mixed>
     */
    private function emptyResult(array $currentFilters): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator([], 0, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);

        return [
            'orders' => $empty,
            'summary' => [
                'total_orders' => 0,
                'masuk' => 0,
                'diproses' => 0,
                'siap_diambil' => 0,
                'selesai' => 0,
                'overdue' => 0,
                'paid_revenue' => 0,
            ],
            'filterOptions' => [
                'outlets' => [],
                'order_statuses' => [],
                'payment_statuses' => [
                    ['value' => 'paid', 'label' => 'Lunas'],
                    ['value' => 'unpaid', 'label' => 'Belum Lunas'],
                ],
            ],
            'currentFilters' => $currentFilters,
            'hasAnyOrders' => false,
        ];
    }
}
