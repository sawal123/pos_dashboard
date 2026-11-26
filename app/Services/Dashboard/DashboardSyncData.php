<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardSyncData
{
    public const PER_PAGE = 25;

    /**
     * Fetch sync requests data scoped to the current business and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'device_id' => isset($filters['device_id']) && $filters['device_id'] !== '' && $filters['device_id'] !== 'all'
                ? (int) $filters['device_id']
                : '',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
            'date' => isset($filters['date']) && in_array($filters['date'], ['all', 'today', '7days', '30days', 'custom'], true)
                ? (string) $filters['date']
                : 'all',
            'start_date' => isset($filters['start_date']) && is_string($filters['start_date']) && trim($filters['start_date']) !== ''
                ? trim($filters['start_date'])
                : '',
            'end_date' => isset($filters['end_date']) && is_string($filters['end_date']) && trim($filters['end_date']) !== ''
                ? trim($filters['end_date'])
                : '',
        ];

        // 1. Guard against null business (no tenant context)
        if ($currentBusiness === null) {
            return [
                'requests' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]),
                'summary' => [
                    'server_sequence' => 0,
                    'total_requests' => 0,
                    'devices_with_push' => 0,
                    'last_processed' => 'Belum Ada',
                ],
                'devices' => [],
                'outlets' => [],
                'currentFilters' => $currentFilters,
                'hasAnyRequests' => false,
            ];
        }

        $businessId = (int) $currentBusiness->id;

        // 2. Unfiltered tenant summary metrics
        $serverSequence = (int) (SyncCounter::where('business_id', $businessId)->value('current_sequence') ?? 0);
        $totalRequests = SyncRequest::where('business_id', $businessId)->count();
        $devicesWithPush = SyncRequest::where('business_id', $businessId)->distinct('device_id')->count('device_id');
        $maxProcessedAt = SyncRequest::where('business_id', $businessId)->max('processed_at');

        $lastProcessedFormatted = 'Belum Ada';
        if ($maxProcessedAt !== null) {
            $lastProcessedFormatted = $this->formatDateTime(Carbon::parse($maxProcessedAt)) ?? 'Belum Ada';
        }

        $hasAnyRequests = $totalRequests > 0;

        $summary = [
            'server_sequence' => $serverSequence,
            'total_requests' => $totalRequests,
            'devices_with_push' => $devicesWithPush,
            'last_processed' => $lastProcessedFormatted,
        ];

        // 3. Filter options (tenant-scoped)
        $devices = Device::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $outlets = Outlet::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        // 4. Base query with eager-loaded relations
        $query = SyncRequest::where('business_id', $businessId)
            ->with(['device', 'device.outlet']);

        // Apply search filter (request_id, device name, device identifier grouped OR)
        if ($currentFilters['q'] !== '') {
            $searchTerm = $currentFilters['q'];
            $query->where(function (Builder $sub) use ($searchTerm, $businessId) {
                $sub->where('request_id', 'like', "%{$searchTerm}%")
                    ->orWhereHas('device', function (Builder $deviceQuery) use ($searchTerm, $businessId) {
                        $deviceQuery->where('business_id', $businessId)
                            ->where(function (Builder $dq) use ($searchTerm) {
                                $dq->where('name', 'like', "%{$searchTerm}%")
                                    ->orWhere('identifier', 'like', "%{$searchTerm}%");
                            });
                    });
            });
        }

        // Apply device filter
        if ($currentFilters['device_id'] !== '') {
            $query->where('device_id', $currentFilters['device_id']);
        }

        // Apply outlet filter via device relation
        if ($currentFilters['outlet_id'] !== '') {
            $outletId = $currentFilters['outlet_id'];
            $query->whereHas('device', function (Builder $deviceQuery) use ($outletId, $businessId) {
                $deviceQuery->where('business_id', $businessId)
                    ->where('outlet_id', $outletId);
            });
        }

        // Apply date preset filter
        match ($currentFilters['date']) {
            'today' => $query->whereBetween('processed_at', [
                now()->startOfDay(),
                now()->endOfDay(),
            ]),
            '7days' => $query->whereBetween('processed_at', [
                now()->subDays(6)->startOfDay(),
                now()->endOfDay(),
            ]),
            '30days' => $query->whereBetween('processed_at', [
                now()->subDays(29)->startOfDay(),
                now()->endOfDay(),
            ]),
            'custom' => $this->applyCustomDateFilter($query, $currentFilters['start_date'], $currentFilters['end_date']),
            default => null,
        };

        // 5. Default sorting and server-side pagination
        $paginator = $query->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // 6. Presentation mapping
        $paginator->through(fn (SyncRequest $req) => $this->presentSyncRequest($req, $businessId));

        return [
            'requests' => $paginator,
            'summary' => $summary,
            'devices' => $devices,
            'outlets' => $outlets,
            'currentFilters' => $currentFilters,
            'hasAnyRequests' => $hasAnyRequests,
        ];
    }

    /**
     * Apply custom date filter safely, handling swapping if start > end.
     *
     * @param  Builder<SyncRequest>  $query
     */
    protected function applyCustomDateFilter(Builder $query, string $startDate, string $endDate): void
    {
        $hasStart = false;
        $hasEnd = false;
        $start = null;
        $end = null;

        if ($startDate !== '') {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $startDate)?->startOfDay();
                $hasStart = $start !== null;
            } catch (\Throwable) {
                $hasStart = false;
            }
        }

        if ($endDate !== '') {
            try {
                $end = Carbon::createFromFormat('Y-m-d', $endDate)?->endOfDay();
                $hasEnd = $end !== null;
            } catch (\Throwable) {
                $hasEnd = false;
            }
        }

        if ($hasStart && $hasEnd && $start !== null && $end !== null) {
            if ($start->gt($end)) {
                $temp = $start->copy()->endOfDay();
                $start = $end->copy()->startOfDay();
                $end = $temp;
            }
            $query->whereBetween('processed_at', [$start, $end]);
        } elseif ($hasStart && $start !== null) {
            $query->where('processed_at', '>=', $start);
        } elseif ($hasEnd && $end !== null) {
            $query->where('processed_at', '<=', $end);
        }
    }

    /**
     * Format a SyncRequest model into a presentation array.
     *
     * @return array<string, mixed>
     */
    public function presentSyncRequest(SyncRequest $req, int $businessId): array
    {
        $device = ($req->device !== null && (int) $req->device->business_id === $businessId)
            ? $req->device
            : null;

        $outlet = ($device !== null && $device->outlet !== null && (int) $device->outlet->business_id === $businessId)
            ? $device->outlet
            : null;

        return [
            'id' => (int) $req->id,
            'request_id' => (string) $req->request_id,
            'device_id' => $device !== null ? (int) $device->id : null,
            'device_name' => $device !== null ? (string) $device->name : '-',
            'device_identifier' => $device !== null ? (string) $device->identifier : '-',
            'outlet_id' => $outlet !== null ? (int) $outlet->id : null,
            'outlet_name' => $outlet !== null ? (string) $outlet->name : '-',
            'processed_at_raw' => $req->processed_at->format('Y-m-d H:i:s'),
            'processed_at' => $this->formatDateTime($req->processed_at) ?? '-',
        ];
    }

    /**
     * Normalize a stored push payload into the drawer's sync-content shape.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public function presentSyncContent(?array $payload): array
    {
        $payload = $payload ?? [];

        $summary = [];
        foreach (['categories', 'products', 'customers', 'shifts', 'sales', 'sale_items', 'expenses', 'cash_ledger', 'stock_movements', 'promos', 'deletions'] as $entity) {
            $summary[$entity] = isset($payload[$entity]) && is_array($payload[$entity])
                ? count($payload[$entity])
                : 0;
        }

        $itemsBySale = [];
        foreach (($payload['sale_items'] ?? []) as $item) {
            $saleSyncId = isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : '';
            if ($saleSyncId === '') {
                continue;
            }

            $itemsBySale[$saleSyncId][] = [
                'product_name' => (string) ($item['product_name'] ?? '-'),
                'product_sku' => (string) ($item['product_sku'] ?? '-'),
                'quantity' => $item['quantity'] ?? 0,
                'unit_price' => (int) ($item['unit_price'] ?? 0),
                'line_total' => (int) ($item['line_total'] ?? 0),
            ];
        }

        $invoices = [];
        foreach (($payload['sales'] ?? []) as $sale) {
            $syncId = (string) ($sale['sync_id'] ?? '');

            $invoices[] = [
                'sync_id' => $syncId,
                'transaction_number' => (string) ($sale['transaction_number'] ?? '-'),
                'status' => (string) ($sale['status'] ?? ''),
                'sold_at' => isset($sale['sold_at']) ? (string) $sale['sold_at'] : null,
                'payment_method' => isset($sale['payment_method']) ? (string) $sale['payment_method'] : null,
                'payment_status' => isset($sale['payment_status']) ? (string) $sale['payment_status'] : null,
                'subtotal' => (int) ($sale['subtotal'] ?? 0),
                'discount_amount' => (int) ($sale['discount_amount'] ?? 0),
                'tax_amount' => (int) ($sale['tax_amount'] ?? 0),
                'total_amount' => (int) ($sale['total_amount'] ?? 0),
                'items' => $itemsBySale[$syncId] ?? [],
            ];
        }

        return [
            'summary' => $summary,
            'invoices' => $invoices,
        ];
    }

    protected function formatDateTime(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $c = Carbon::instance($date)->setTimezone($tz);
        $c->setLocale('id');

        return $c->translatedFormat('d M Y · H:i');
    }
}
