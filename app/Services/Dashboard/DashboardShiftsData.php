<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Shift;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardShiftsData
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

        $baseQuery = Shift::query()->where('business_id', $businessId);
        $summary = [
            'total_shifts' => (clone $baseQuery)->count(),
            'open_shifts' => (clone $baseQuery)->where('status', 'open')->count(),
            'closed_shifts' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        $hasAnyShifts = $summary['total_shifts'] > 0;

        $outlets = Outlet::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $statuses = Shift::query()
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

        $query = Shift::query()
            ->where('business_id', $businessId)
            ->with('outlet');

        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, Shift> $paginator */
        $paginator = $query
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $shiftIds = $paginator->getCollection()->pluck('id')->map(fn (int $id): int => $id)->all();
        $aggregates = $this->aggregatesForShiftIds($businessId, $shiftIds);

        $paginator->getCollection()->transform(function (Shift $shift) use ($businessId, $aggregates): array {
            return $this->presentShift($shift, $businessId, $aggregates[(int) $shift->id] ?? $this->emptyAggregates());
        });

        return [
            'shifts' => $paginator,
            'summary' => $summary,
            'filterOptions' => [
                'outlets' => $outlets,
                'statuses' => $statuses,
            ],
            'currentFilters' => $currentFilters,
            'hasAnyShifts' => $hasAnyShifts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Business $currentBusiness, int $shiftId): array
    {
        /** @var Shift|null $shift */
        $shift = Shift::query()
            ->where('business_id', $currentBusiness->id)
            ->where('id', $shiftId)
            ->with('outlet')
            ->first();

        if ($shift === null) {
            abort(404);
        }

        $aggregates = $this->aggregatesForShiftIds((int) $currentBusiness->id, [(int) $shift->id]);

        return $this->presentShift(
            $shift,
            (int) $currentBusiness->id,
            $aggregates[(int) $shift->id] ?? $this->emptyAggregates()
        );
    }

    /**
     * @param  Builder<Shift>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $q = (string) $filters['q'];
            $query->where('shift_number', 'like', "%{$q}%");
        }

        if ($filters['outlet_id'] !== '') {
            $query->where('outlet_id', (int) $filters['outlet_id']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', (string) $filters['status']);
        }

        if ($filters['date'] !== 'all') {
            $this->applyDateFilter($query, (string) $filters['date'], $filters);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, string $date, array $filters): void
    {
        $tz = config('app.timezone', 'UTC');
        $today = Carbon::now($tz)->startOfDay();

        match ($date) {
            'today' => $query->whereBetween('opened_at', [
                $today->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '7d' => $query->whereBetween('opened_at', [
                $today->copy()->subDays(6)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '30d' => $query->whereBetween('opened_at', [
                $today->copy()->subDays(29)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            'custom' => $this->applyCustomDateFilter($query, $filters, $tz),
            default => null,
        };
    }

    /**
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

        if ($startDate !== null && $endDate !== null && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        if ($startDate !== null) {
            $query->where('opened_at', '>=', $startDate->toDateTimeString());
        }

        if ($endDate !== null) {
            $query->where('opened_at', '<=', $endDate->toDateTimeString());
        }
    }

    /**
     * @param  array<int, int>  $shiftIds
     * @return array<int, array<string, int>>
     */
    private function aggregatesForShiftIds(int $businessId, array $shiftIds): array
    {
        if ($shiftIds === []) {
            return [];
        }

        $aggregates = [];
        foreach ($shiftIds as $shiftId) {
            $aggregates[$shiftId] = $this->emptyAggregates();
        }

        $sales = DB::table('sales')
            ->where('business_id', $businessId)
            ->whereIn('shift_id', $shiftIds)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->select('shift_id', DB::raw('COUNT(*) as aggregate_count'), DB::raw('COALESCE(SUM(total_amount), 0) as aggregate_total'))
            ->groupBy('shift_id')
            ->get();

        foreach ($sales as $sale) {
            $shiftId = (int) $sale->shift_id;
            $aggregates[$shiftId]['sales_count'] = (int) $sale->aggregate_count;
            $aggregates[$shiftId]['sales_total'] = (int) $sale->aggregate_total;
        }

        $cashLedgers = DB::table('cash_ledger')
            ->where('business_id', $businessId)
            ->whereIn('shift_id', $shiftIds)
            ->select(
                'shift_id',
                DB::raw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as cash_in"),
                DB::raw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as cash_out")
            )
            ->groupBy('shift_id')
            ->get();

        foreach ($cashLedgers as $ledger) {
            $shiftId = (int) $ledger->shift_id;
            $aggregates[$shiftId]['cash_in'] = (int) $ledger->cash_in;
            $aggregates[$shiftId]['cash_out'] = (int) $ledger->cash_out;
        }

        $expenses = DB::table('expenses')
            ->where('business_id', $businessId)
            ->whereIn('shift_id', $shiftIds)
            ->where('status', 'recorded')
            ->select('shift_id', DB::raw('COALESCE(SUM(amount), 0) as expense_total'))
            ->groupBy('shift_id')
            ->get();

        foreach ($expenses as $expense) {
            $shiftId = (int) $expense->shift_id;
            $aggregates[$shiftId]['expense_total'] = (int) $expense->expense_total;
        }

        return $aggregates;
    }

    /**
     * @param  array<string, int>  $aggregates
     * @return array<string, mixed>
     */
    private function presentShift(Shift $shift, int $businessId, array $aggregates): array
    {
        $statusRaw = (string) $shift->status;
        $openingCash = (int) $shift->opening_cash;
        $cashIn = $aggregates['cash_in'];
        $cashOut = $aggregates['cash_out'];

        $outletName = ($shift->outlet !== null && (int) $shift->outlet->business_id === $businessId)
            ? $shift->outlet->name
            : '-';

        return [
            'id' => (int) $shift->id,
            'shift_number' => (string) $shift->shift_number,
            'outlet_id' => (int) $shift->outlet_id,
            'outlet_name' => $outletName,
            'status_raw' => $statusRaw,
            'status' => $this->presentStatus($statusRaw),
            'opened_at_raw' => $shift->opened_at->format('Y-m-d H:i:s'),
            'opened_at' => $this->formatDateTime($shift->opened_at),
            'closed_at_raw' => $shift->closed_at?->format('Y-m-d H:i:s'),
            'closed_at' => $this->formatDateTime($shift->closed_at) ?? '-',
            'opening_cash' => $openingCash,
            'closing_cash' => $shift->closing_cash !== null ? (int) $shift->closing_cash : null,
            'sales_count' => $aggregates['sales_count'],
            'sales_total' => $aggregates['sales_total'],
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expense_total' => $aggregates['expense_total'],
            'estimated_cash' => $openingCash + $cashIn - $cashOut,
            'notes' => $shift->notes !== null ? (string) $shift->notes : null,
        ];
    }

    private function presentStatus(string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'closed' => 'Closed',
            default => ucwords(str_replace(['_', '-'], ' ', $status)),
        };
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
     * @return array<string, int>
     */
    private function emptyAggregates(): array
    {
        return [
            'sales_count' => 0,
            'sales_total' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expense_total' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
            'status' => isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
                ? (string) $filters['status']
                : 'all',
            'date' => isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'all',
            'start_date' => isset($filters['start_date']) ? (string) $filters['start_date'] : '',
            'end_date' => isset($filters['end_date']) ? (string) $filters['end_date'] : '',
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
            'shifts' => $empty,
            'summary' => [
                'total_shifts' => 0,
                'open_shifts' => 0,
                'closed_shifts' => 0,
            ],
            'filterOptions' => [
                'outlets' => [],
                'statuses' => [],
            ],
            'currentFilters' => $currentFilters,
            'hasAnyShifts' => false,
        ];
    }
}
