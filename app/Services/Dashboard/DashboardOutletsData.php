<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, tenant-scoped outlet monitoring data.
 *
 * All metrics are derived from synced rows for the current business only. Sales
 * metrics always mean "completed + paid" transactions; revenue and CashLedger
 * mutations are intentionally kept separate to avoid double-counting.
 */
class DashboardOutletsData
{
    public const PER_PAGE = 25;

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

        $summary = $this->summary($businessId);

        $statuses = Outlet::query()
            ->where('business_id', $businessId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $this->presentStatus($status),
            ])
            ->values()
            ->toArray();

        $query = Outlet::query()->where('business_id', $businessId);
        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, Outlet> $paginator */
        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $outletIds = $paginator->getCollection()
            ->pluck('id')
            ->map(fn (int $id): int => (int) $id)
            ->all();

        $aggregates = $this->listAggregates($businessId, $outletIds);

        $paginator->getCollection()->transform(function (Outlet $outlet) use ($aggregates): array {
            return $this->presentOutlet(
                $outlet,
                $aggregates[(int) $outlet->id] ?? $this->emptyListAggregates()
            );
        });

        return [
            'outlets' => $paginator,
            'summary' => $summary,
            'filterOptions' => [
                'statuses' => $statuses,
            ],
            'currentFilters' => $currentFilters,
            'hasAnyOutlets' => $summary['total_outlets'] > 0,
        ];
    }

    /**
     * Read-only detail for a single outlet belonging to the current business.
     * A foreign or unknown outlet id aborts with 404.
     *
     * @return array<string, mixed>
     */
    public function detail(Business $currentBusiness, int $outletId): array
    {
        $businessId = (int) $currentBusiness->id;

        /** @var Outlet|null $outlet */
        $outlet = Outlet::query()
            ->where('business_id', $businessId)
            ->where('id', $outletId)
            ->first();

        if ($outlet === null) {
            abort(404);
        }

        $deviceCount = Device::query()
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->count();

        $totalShifts = Shift::query()
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->count();

        $openShifts = Shift::query()
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->where('status', 'open')
            ->count();

        $sales = DB::table('sales')
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->select(
                DB::raw('COUNT(*) as aggregate_count'),
                DB::raw('COALESCE(SUM(total_amount), 0) as aggregate_total'),
                DB::raw('MAX(sold_at) as last_sold_at')
            )
            ->first();

        $cash = DB::table('cash_ledger')
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->select(
                DB::raw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as cash_in"),
                DB::raw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as cash_out")
            )
            ->first();

        $expenseTotal = (int) DB::table('expenses')
            ->where('business_id', $businessId)
            ->where('outlet_id', $outletId)
            ->where('status', 'recorded')
            ->sum('amount');

        $salesCount = $sales !== null ? (int) $sales->aggregate_count : 0;
        $salesTotal = $sales !== null ? (int) $sales->aggregate_total : 0;
        $lastSoldAt = $sales !== null ? $sales->last_sold_at : null;

        $statusRaw = (string) $outlet->status;

        return [
            'id' => (int) $outlet->id,
            'name' => (string) $outlet->name,
            'code' => (string) $outlet->code,
            'address' => $outlet->address !== null && trim((string) $outlet->address) !== ''
                ? (string) $outlet->address
                : null,
            'status_raw' => $statusRaw,
            'status' => $this->presentStatus($statusRaw),
            'device_count' => $deviceCount,
            'total_shifts' => $totalShifts,
            'open_shifts' => $openShifts,
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'cash_in' => $cash !== null ? (int) $cash->cash_in : 0,
            'cash_out' => $cash !== null ? (int) $cash->cash_out : 0,
            'expense_total' => $expenseTotal,
            'last_transaction_at_raw' => $lastSoldAt !== null ? (string) $lastSoldAt : null,
            'last_transaction_at' => $this->formatDateTime($lastSoldAt),
        ];
    }

    /**
     * Tenant-scoped summary metrics. Only explicit `active` / `inactive`
     * statuses are counted; any other status is counted in neither bucket.
     *
     * @return array<string, int>
     */
    private function summary(int $businessId): array
    {
        $base = Outlet::query()->where('business_id', $businessId);

        return [
            'total_outlets' => (clone $base)->count(),
            'active_outlets' => (clone $base)->where('status', 'active')->count(),
            'inactive_outlets' => (clone $base)->where('status', 'inactive')->count(),
            'outlets_with_open_shift' => Shift::query()
                ->where('business_id', $businessId)
                ->where('status', 'open')
                ->distinct()
                ->count('outlet_id'),
        ];
    }

    /**
     * @param  Builder<Outlet>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $search = (string) $filters['q'];
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', (string) $filters['status']);
        }
    }

    /**
     * Batch aggregates for the visible outlet page (no N+1).
     *
     * @param  array<int, int>  $outletIds
     * @return array<int, array<string, mixed>>
     */
    private function listAggregates(int $businessId, array $outletIds): array
    {
        if ($outletIds === []) {
            return [];
        }

        $aggregates = [];
        foreach ($outletIds as $outletId) {
            $aggregates[$outletId] = $this->emptyListAggregates();
        }

        $devices = DB::table('devices')
            ->where('business_id', $businessId)
            ->whereIn('outlet_id', $outletIds)
            ->select('outlet_id', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('outlet_id')
            ->get();

        foreach ($devices as $row) {
            $aggregates[(int) $row->outlet_id]['device_count'] = (int) $row->aggregate_count;
        }

        $openShifts = DB::table('shifts')
            ->where('business_id', $businessId)
            ->whereIn('outlet_id', $outletIds)
            ->where('status', 'open')
            ->select('outlet_id', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('outlet_id')
            ->get();

        foreach ($openShifts as $row) {
            $aggregates[(int) $row->outlet_id]['open_shift_count'] = (int) $row->aggregate_count;
        }

        $sales = DB::table('sales')
            ->where('business_id', $businessId)
            ->whereIn('outlet_id', $outletIds)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->select(
                'outlet_id',
                DB::raw('COUNT(*) as aggregate_count'),
                DB::raw('COALESCE(SUM(total_amount), 0) as aggregate_total'),
                DB::raw('MAX(sold_at) as last_sold_at')
            )
            ->groupBy('outlet_id')
            ->get();

        foreach ($sales as $row) {
            $outletId = (int) $row->outlet_id;
            $aggregates[$outletId]['sales_count'] = (int) $row->aggregate_count;
            $aggregates[$outletId]['sales_total'] = (int) $row->aggregate_total;
            $aggregates[$outletId]['last_transaction_at_raw'] = $row->last_sold_at !== null
                ? (string) $row->last_sold_at
                : null;
        }

        return $aggregates;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return array<string, mixed>
     */
    private function presentOutlet(Outlet $outlet, array $aggregates): array
    {
        $statusRaw = (string) $outlet->status;

        return [
            'id' => (int) $outlet->id,
            'name' => (string) $outlet->name,
            'code' => (string) $outlet->code,
            'address' => $outlet->address !== null && trim((string) $outlet->address) !== ''
                ? (string) $outlet->address
                : null,
            'status_raw' => $statusRaw,
            'status' => $this->presentStatus($statusRaw),
            'device_count' => $aggregates['device_count'],
            'open_shift_count' => $aggregates['open_shift_count'],
            'sales_count' => $aggregates['sales_count'],
            'sales_total' => $aggregates['sales_total'],
            'last_transaction_at_raw' => $aggregates['last_transaction_at_raw'],
            'last_transaction_at' => $this->formatDateTime($aggregates['last_transaction_at_raw']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyListAggregates(): array
    {
        return [
            'device_count' => 0,
            'open_shift_count' => 0,
            'sales_count' => 0,
            'sales_total' => 0,
            'last_transaction_at_raw' => null,
        ];
    }

    private function presentStatus(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => $status === '' ? 'Tidak Diketahui' : ucwords(str_replace(['_', '-'], ' ', $status)),
        };
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = Carbon::parse((string) $value);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y - H:i');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'status' => isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
                ? (string) $filters['status']
                : 'all',
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
            'outlets' => $empty,
            'summary' => [
                'total_outlets' => 0,
                'active_outlets' => 0,
                'inactive_outlets' => 0,
                'outlets_with_open_shift' => 0,
            ],
            'filterOptions' => [
                'statuses' => [],
            ],
            'currentFilters' => $currentFilters,
            'hasAnyOutlets' => false,
        ];
    }
}
