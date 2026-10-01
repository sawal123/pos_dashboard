<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Subscription;
use App\Services\Subscription\CloudDeviceLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DevicesController extends Controller
{
    public function __construct(
        private readonly CloudDeviceLimit $deviceLimit,
    ) {}

    /**
     * Display a listing of all cloud devices across businesses with search, filters, and quota context.
     */
    public function index(Request $request): View
    {
        $query = Device::query()->with(['business.subscription', 'outlet']);

        // Search across device name, identifier, business name, business slug, and outlet name
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function (Builder $builder) use ($q): void {
                $builder->where('devices.name', 'like', "%{$q}%")
                    ->orWhere('devices.identifier', 'like', "%{$q}%")
                    ->orWhereHas('business', function (Builder $b) use ($q): void {
                        $b->where('name', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%");
                    })
                    ->orWhereHas('outlet', function (Builder $o) use ($q): void {
                        $o->where('name', 'like', "%{$q}%");
                    });
            });
        }

        // Filter by device status (active / inactive)
        $status = $request->input('status');
        if (is_string($status) && in_array($status, Device::STATUSES, true)) {
            $query->where('devices.status', $status);
        }

        // Filter by business cloud entitlement status
        $entitlement = $request->input('entitlement');
        if ($entitlement === 'cloud_active') {
            $query->whereHas('business.subscription', function (Builder $s): void {
                $s->where('plan', Subscription::PLAN_CLOUD)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->where(function (Builder $exp): void {
                        $exp->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            });
        } elseif ($entitlement === 'cloud_denied') {
            $query->where(function (Builder $b): void {
                $b->whereDoesntHave('business.subscription')
                    ->orWhereHas('business.subscription', function (Builder $s): void {
                        $s->where('plan', '!=', Subscription::PLAN_CLOUD)
                            ->orWhere('status', '!=', Subscription::STATUS_ACTIVE)
                            ->orWhere(function (Builder $exp): void {
                                $exp->whereNotNull('expires_at')->where('expires_at', '<=', now());
                            });
                    });
            });
        }

        $devices = $query->latest('devices.id')->paginate(25)->withQueryString();

        // Summary cards metrics (0 N+1)
        $limit = $this->deviceLimit->limit();
        $summary = [
            'total' => Device::query()->count(),
            'active' => Device::query()->where('status', Device::STATUS_ACTIVE)->count(),
            'inactive' => Device::query()->where('status', Device::STATUS_INACTIVE)->count(),
            'at_limit' => $limit > 0 ? DB::query()->fromSub(
                Device::query()
                    ->where('status', Device::STATUS_ACTIVE)
                    ->select('business_id')
                    ->groupBy('business_id')
                    ->havingRaw('count(*) >= ?', [$limit]),
                'businesses_at_limit'
            )->count() : 0,
        ];

        // Eager-compute active device counts for businesses on the current page (1 query, 0 N+1)
        $pageBusinessIds = $devices->pluck('business_id')->unique()->filter()->values();
        $activeCounts = Device::query()
            ->whereIn('business_id', $pageBusinessIds)
            ->where('status', Device::STATUS_ACTIVE)
            ->selectRaw('business_id, count(*) as count')
            ->groupBy('business_id')
            ->pluck('count', 'business_id')
            ->all();

        return view('platform.devices.index', [
            'devices' => $devices,
            'summary' => $summary,
            'activeCounts' => $activeCounts,
            'limit' => $limit,
            'currentQ' => $q,
            'currentStatus' => $status,
            'currentEntitlement' => $entitlement,
        ]);
    }

    /**
     * Display detailed device information, associated business and outlet, and authoritative quota context.
     */
    public function show(Device $device): View
    {
        $device->load(['business.subscription', 'outlet']);

        $business = $device->business;
        $hasCloudAccess = $business ? $business->hasCloudAccess() : false;
        $limit = $this->deviceLimit->limit();
        $activeCount = $business ? $this->deviceLimit->activeCount($business) : 0;
        $remaining = $business ? $this->deviceLimit->remaining($business) : null;
        $isReached = $business ? $this->deviceLimit->isReached($business) : false;
        $isCounted = $device->status === Device::STATUS_ACTIVE;

        return view('platform.devices.show', [
            'device' => $device,
            'business' => $business,
            'hasCloudAccess' => $hasCloudAccess,
            'limit' => $limit,
            'activeCount' => $activeCount,
            'remaining' => $remaining,
            'isReached' => $isReached,
            'isCounted' => $isCounted,
        ]);
    }

    /**
     * Deactivate / revoke device cloud access.
     * Keeps history, data, business, and outlet untouched. Reduces active quota count.
     * Does not require business to have active Cloud subscription.
     */
    public function deactivate(Device $device): RedirectResponse
    {
        if ($device->status === Device::STATUS_INACTIVE) {
            return back()->with('info', 'Perangkat sudah dalam status nonaktif.');
        }

        $device->update(['status' => Device::STATUS_INACTIVE]);

        return back()->with('success', 'Perangkat "'.$device->name.'" berhasil dinonaktifkan dari akses Cloud.');
    }

    /**
     * Reactivate an inactive device.
     * Requires business to hold active Cloud entitlement AND have remaining quota slots.
     * Serialized via parent Business row lock.
     */
    public function activate(Device $device): RedirectResponse
    {
        if ($device->status === Device::STATUS_ACTIVE) {
            return back()->with('info', 'Perangkat sudah dalam status aktif.');
        }

        $business = $device->business;

        if (! $business || ! $business->hasCloudAccess()) {
            return back()->with('error', 'Bisnis tidak memiliki akses Cloud aktif. Langganan Cloud aktif diperlukan untuk mengaktifkan kembali perangkat.');
        }

        try {
            DB::transaction(function () use ($device): void {
                /** @var Business $lockedBusiness */
                $lockedBusiness = Business::query()
                    ->whereKey($device->business_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedBusiness->hasCloudAccess()) {
                    throw new \RuntimeException('Bisnis tidak memiliki akses Cloud aktif.');
                }

                if ($this->deviceLimit->isReached($lockedBusiness)) {
                    throw new \RuntimeException('Batas perangkat Cloud tercapai (maksimal '.$this->deviceLimit->limit().' perangkat aktif). Nonaktifkan perangkat lain terlebih dahulu.');
                }

                $device->update(['status' => Device::STATUS_ACTIVE]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Perangkat "'.$device->name.'" berhasil diaktifkan kembali.');
    }
}
