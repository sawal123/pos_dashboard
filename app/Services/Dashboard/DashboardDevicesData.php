<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DashboardDevicesData
{
    public const PER_PAGE = 25;

    /**
     * Fetch devices data scoped to the current business and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
            'status' => isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
                ? (string) $filters['status']
                : 'all',
            'platform' => isset($filters['platform']) && $filters['platform'] !== '' && $filters['platform'] !== 'all'
                ? (string) $filters['platform']
                : 'all',
        ];

        // 1. Guard against null business (no tenant context)
        if ($currentBusiness === null) {
            return [
                'devices' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]),
                'summary' => [
                    'total_devices' => 0,
                    'active_devices' => 0,
                    'inactive_devices' => 0,
                    'never_seen_devices' => 0,
                ],
                'outlets' => [],
                'platforms' => [],
                'currentFilters' => $currentFilters,
                'hasAnyDevices' => false,
            ];
        }

        $businessId = (int) $currentBusiness->id;

        // 2. Unfiltered tenant summary metrics
        $totalDevices = Device::where('business_id', $businessId)->count();
        $activeDevices = Device::where('business_id', $businessId)->where('status', 'active')->count();
        $inactiveDevices = Device::where('business_id', $businessId)->where('status', 'inactive')->count();
        $neverSeenDevices = Device::where('business_id', $businessId)->whereNull('last_seen_at')->count();

        $hasAnyDevices = $totalDevices > 0;

        $summary = [
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'inactive_devices' => $inactiveDevices,
            'never_seen_devices' => $neverSeenDevices,
        ];

        // 3. Filter options (tenant-scoped)
        $outlets = Outlet::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $platforms = Device::where('business_id', $businessId)
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform')
            ->toArray();

        // 4. Base query with eager-loaded relation
        $query = Device::where('business_id', $businessId)->with('outlet');

        // Apply search filter (name & identifier grouped OR)
        if ($currentFilters['q'] !== '') {
            $searchTerm = $currentFilters['q'];
            $query->where(function (Builder $sub) use ($searchTerm) {
                $sub->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('identifier', 'like', "%{$searchTerm}%");
            });
        }

        // Apply outlet filter
        if ($currentFilters['outlet_id'] !== '') {
            $query->where('outlet_id', $currentFilters['outlet_id']);
        }

        // Apply status filter (exact raw value match)
        if ($currentFilters['status'] !== 'all') {
            $query->where('status', $currentFilters['status']);
        }

        // Apply platform filter (exact raw value match)
        if ($currentFilters['platform'] !== 'all') {
            $query->where('platform', $currentFilters['platform']);
        }

        // 5. Default sorting and server-side pagination
        $paginator = $query->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // 6. Presentation mapping
        $paginator->through(fn (Device $device) => $this->presentDevice($device, $businessId));

        return [
            'devices' => $paginator,
            'summary' => $summary,
            'outlets' => $outlets,
            'platforms' => $platforms,
            'currentFilters' => $currentFilters,
            'hasAnyDevices' => $hasAnyDevices,
        ];
    }

    /**
     * Format a Device model into a presentation array.
     *
     * @return array<string, mixed>
     */
    public function presentDevice(Device $device, int $businessId): array
    {
        $statusRaw = (string) $device->status;
        $statusLabel = match ($statusRaw) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => ucwords(str_replace(['_', '-'], ' ', $statusRaw)),
        };

        $platformRaw = $device->platform !== null ? (string) $device->platform : null;
        $platformLabel = $platformRaw !== null && trim($platformRaw) !== ''
            ? $platformRaw
            : 'Tidak Diketahui';

        $outletName = ($device->outlet !== null && (int) $device->outlet->business_id === $businessId)
            ? $device->outlet->name
            : '-';

        return [
            'id' => (int) $device->id,
            'name' => (string) $device->name,
            'identifier' => (string) $device->identifier,
            'outlet_id' => (int) $device->outlet_id,
            'outlet_name' => $outletName,
            'platform_raw' => $platformRaw,
            'platform' => $platformLabel,
            'status_raw' => $statusRaw,
            'status' => $statusLabel,
            'registered_at_raw' => $device->registered_at->format('Y-m-d H:i:s'),
            'registered_at' => $this->formatDateTime($device->registered_at) ?? '-',
            'last_seen_at_raw' => $device->last_seen_at?->format('Y-m-d H:i:s'),
            'last_seen_at' => $this->formatDateTime($device->last_seen_at) ?? 'Belum Pernah Terlihat',
            'notes' => $device->notes !== null ? (string) $device->notes : null,
        ];
    }

    protected function formatDateTime(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $c = Carbon::instance($date);
        $c->setLocale('id');

        return $c->translatedFormat('d M Y · H:i');
    }
}
