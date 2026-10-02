<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\SyncRequest;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class PlatformSyncMonitoringData
{
    public const PER_PAGE = 25;

    /**
     * Fetch global sync requests data across all tenants with search, filters, and metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $currentFilters = [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'business_id' => isset($filters['business_id']) && $filters['business_id'] !== '' && $filters['business_id'] !== 'all'
                ? (int) $filters['business_id']
                : '',
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

        // 1. Authoritative global summary metrics (0 fake data, aggregate SQL)
        $totalRequests = SyncRequest::query()->count();
        $businessesWithSync = SyncRequest::query()->distinct('business_id')->count('business_id');
        $devicesWithSync = SyncRequest::query()->distinct('device_id')->count('device_id');
        $maxProcessedAt = SyncRequest::query()->max('processed_at');
        $requestsToday = SyncRequest::query()->whereBetween('processed_at', [
            now()->startOfDay(),
            now()->endOfDay(),
        ])->count();

        $lastProcessedFormatted = 'Belum Ada';
        if ($maxProcessedAt !== null) {
            $lastProcessedFormatted = $this->formatDateTime(Carbon::parse($maxProcessedAt)) ?? 'Belum Ada';
        }

        $summary = [
            'total_requests' => $totalRequests,
            'businesses_with_sync' => $businessesWithSync,
            'devices_with_sync' => $devicesWithSync,
            'requests_today' => $requestsToday,
            'last_processed' => $lastProcessedFormatted,
        ];

        // 2. Filter dropdown options
        $businesses = Business::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $devicesQuery = Device::query();
        if ($currentFilters['business_id'] !== '') {
            $devicesQuery->where('business_id', $currentFilters['business_id']);
        }
        $devices = $devicesQuery->orderBy('name')->get(['id', 'name', 'identifier'])->toArray();

        $outletsQuery = Outlet::query();
        if ($currentFilters['business_id'] !== '') {
            $outletsQuery->where('business_id', $currentFilters['business_id']);
        }
        $outlets = $outletsQuery->orderBy('name')->get(['id', 'name'])->toArray();

        // 3. Base query with eager loading to prevent N+1 queries
        $query = SyncRequest::query()
            ->with(['business', 'device.outlet']);

        // Search across request_id, device name, device identifier, business name, business slug, and outlet name
        if ($currentFilters['q'] !== '') {
            $searchTerm = $currentFilters['q'];
            $query->where(function (Builder $sub) use ($searchTerm): void {
                $sub->where('request_id', 'like', "%{$searchTerm}%")
                    ->orWhereHas('device', function (Builder $dq) use ($searchTerm): void {
                        $dq->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('identifier', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('business', function (Builder $bq) use ($searchTerm): void {
                        $bq->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('slug', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('device.outlet', function (Builder $oq) use ($searchTerm): void {
                        $oq->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Filter by business_id
        if ($currentFilters['business_id'] !== '') {
            $query->where('business_id', $currentFilters['business_id']);
        }

        // Filter by device_id
        if ($currentFilters['device_id'] !== '') {
            $query->where('device_id', $currentFilters['device_id']);
        }

        // Filter by outlet_id via device relation
        if ($currentFilters['outlet_id'] !== '') {
            $outletId = $currentFilters['outlet_id'];
            $query->whereHas('device', function (Builder $dq) use ($outletId): void {
                $dq->where('outlet_id', $outletId);
            });
        }

        // Filter by processed_at date preset or custom range
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

        // 4. Sorting and server-side pagination (25 per page withQueryString)
        $paginator = $query->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // 5. Defensive presentation mapping
        $paginator->through(fn (SyncRequest $req) => $this->presentSyncRequest($req));

        return [
            'requests' => $paginator,
            'summary' => $summary,
            'businesses' => $businesses,
            'devices' => $devices,
            'outlets' => $outlets,
            'currentFilters' => $currentFilters,
            'hasAnyRequests' => $totalRequests > 0,
        ];
    }

    /**
     * Apply custom date filter safely with automatic start/end reversal handling.
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
     * Format a SyncRequest model into a safe presentation array with defensive tenant checking.
     *
     * @return array<string, mixed>
     */
    public function presentSyncRequest(SyncRequest $req): array
    {
        $business = $req->business;

        // Defensive tenant check: verify device actually belongs to sync request business
        $isDeviceValid = $req->device !== null && (int) $req->device->business_id === (int) $req->business_id;
        $device = $isDeviceValid ? $req->device : null;

        // Defensive outlet check: verify outlet belongs to sync request business
        $isOutletValid = $device !== null && $device->outlet !== null && (int) $device->outlet->business_id === (int) $req->business_id;
        $outlet = $isOutletValid ? $device->outlet : null;

        $lastSeenAt = $device?->last_seen_at;
        $activity = self::getActivityDiagnostic($lastSeenAt, $device !== null);

        return [
            'id' => (int) $req->id,
            'request_id' => (string) $req->request_id,
            'status' => 'Committed',
            'business_id' => $business !== null ? (int) $business->id : (int) $req->business_id,
            'business_name' => $business !== null ? (string) $business->name : '-',
            'business_slug' => $business !== null ? (string) $business->slug : '-',
            'device_id' => $device !== null ? (int) $device->id : null,
            'device_name' => $device !== null ? (string) $device->name : ($req->device_id ? 'Perangkat Tidak Valid' : '-'),
            'device_identifier' => $device !== null ? (string) $device->identifier : '-',
            'device_status' => $device !== null ? (string) $device->status : 'Tidak Terhubung',
            'outlet_id' => $outlet !== null ? (int) $outlet->id : null,
            'outlet_name' => $outlet !== null ? (string) $outlet->name : '-',
            'last_seen_at' => $lastSeenAt !== null ? $this->formatDateTime($lastSeenAt) : 'Belum Ada',
            'activity' => $activity,
            'processed_at_raw' => $req->processed_at->format('Y-m-d H:i:s'),
            'processed_at' => $this->formatDateTime($req->processed_at) ?? '-',
        ];
    }

    /**
     * Compute activity diagnostic safely without pretending to be a sync health score.
     *
     * @return array{status: string, label: string, badge_class: string}
     */
    public static function getActivityDiagnostic(?DateTimeInterface $lastSeenAt, bool $hasDevice = true): array
    {
        if (! $hasDevice) {
            return [
                'status' => 'no_device',
                'label' => 'Tidak Terhubung',
                'badge_class' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
            ];
        }

        if ($lastSeenAt === null) {
            return [
                'status' => 'never',
                'label' => 'Belum Ada',
                'badge_class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
            ];
        }

        $c = Carbon::instance($lastSeenAt);

        if ($c->greaterThanOrEqualTo(now()->subHours(24))) {
            return [
                'status' => 'recent',
                'label' => 'Aktif (< 24 jam)',
                'badge_class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60',
            ];
        }

        return [
            'status' => 'stale',
            'label' => 'Stale (> 24 jam)',
            'badge_class' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60',
        ];
    }

    public function formatDateTime(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $c = Carbon::instance($date);
        $c->setLocale('id');

        return $c->translatedFormat('d M Y · H:i');
    }
}
