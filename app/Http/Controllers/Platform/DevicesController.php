<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Subscription\CloudDeviceLimit;
use App\Support\PlatformAuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $device->load(['business.subscription', 'business.outlets', 'outlet']);

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
     * Update editable device metadata. Lifecycle status continues to use the
     * quota-aware activate/deactivate actions below.
     */
    public function update(
        Request $request,
        Device $device,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'identifier' => [
                'required',
                'string',
                'max:190',
                Rule::unique('devices', 'identifier')
                    ->where(fn ($query) => $query->where('business_id', $device->business_id))
                    ->ignore($device),
            ],
            'platform' => ['nullable', 'string', 'max:80'],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($query) => $query->where('business_id', $device->business_id)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $device->only(['name', 'identifier', 'platform', 'outlet_id', 'notes']);
        $device->update($validated);

        /** @var User $actor */
        $actor = $request->user();
        $auditLogger->record(
            actor: $actor,
            action: PlatformAuditAction::DEVICE_UPDATED,
            targetType: PlatformAuditAction::TARGET_DEVICE,
            targetId: $device->id,
            targetLabel: $device->name,
            businessId: $device->business_id,
            before: $before,
            after: $device->only(['name', 'identifier', 'platform', 'outlet_id', 'notes']),
        );

        return redirect()->route('platform.devices.show', $device)
            ->with('status', 'Data perangkat berhasil diperbarui.');
    }

    /**
     * Delete a device and its committed sync-request records. Sales and other
     * business history remain untouched.
     */
    public function destroy(
        Request $request,
        Device $device,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);

        $deviceId = $device->id;
        $deviceName = $device->name;
        $businessId = $device->business_id;
        $before = ['name' => $device->name, 'identifier' => $device->identifier, 'status' => $device->status];

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($device, $auditLogger, $actor, $deviceId, $deviceName, $businessId, $before): void {
            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::DEVICE_DELETED,
                targetType: PlatformAuditAction::TARGET_DEVICE,
                targetId: $deviceId,
                targetLabel: $deviceName,
                businessId: $businessId,
                before: $before,
                after: null,
                metadata: ['destructive_action' => true, 'sync_history_deleted' => true],
            );
            $device->delete();
        });

        return redirect()->route('platform.devices.index')
            ->with('status', "Perangkat '{$deviceName}' berhasil dihapus.");
    }

    /**
     * Deactivate / revoke device cloud access.
     * Keeps history, data, business, and outlet untouched. Reduces active quota count.
     * Does not require business to have active Cloud subscription.
     */
    public function deactivate(Request $request, Device $device, PlatformAuditLogger $auditLogger): RedirectResponse
    {
        if ($device->status === Device::STATUS_INACTIVE) {
            return back()->with('info', 'Perangkat sudah dalam status nonaktif.');
        }

        DB::transaction(function () use ($request, $device, $auditLogger): void {
            $before = ['status' => $device->status];
            $device->update(['status' => Device::STATUS_INACTIVE]);
            $after = ['status' => Device::STATUS_INACTIVE];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::DEVICE_DEACTIVATED,
                targetType: PlatformAuditAction::TARGET_DEVICE,
                targetId: $device->id,
                targetLabel: $device->name,
                businessId: $device->business_id,
                before: $before,
                after: $after,
                metadata: [
                    'device_identifier' => $device->identifier,
                ],
            );
        });

        return back()->with('success', 'Perangkat "'.$device->name.'" berhasil dinonaktifkan dari akses Cloud.');
    }

    /**
     * Reactivate an inactive device.
     * Requires business to hold active Cloud entitlement AND have remaining quota slots.
     * Serialized via parent Business row lock.
     */
    public function activate(Request $request, Device $device, PlatformAuditLogger $auditLogger): RedirectResponse
    {
        if ($device->status === Device::STATUS_ACTIVE) {
            return back()->with('info', 'Perangkat sudah dalam status aktif.');
        }

        $business = $device->business;

        if (! $business || ! $business->hasCloudAccess()) {
            return back()->with('error', 'Bisnis tidak memiliki akses Cloud aktif. Langganan Cloud aktif diperlukan untuk mengaktifkan kembali perangkat.');
        }

        try {
            DB::transaction(function () use ($request, $device, $auditLogger): void {
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

                $before = ['status' => $device->status];
                $device->update(['status' => Device::STATUS_ACTIVE]);
                $after = ['status' => Device::STATUS_ACTIVE];

                /** @var User $actor */
                $actor = $request->user();

                $auditLogger->record(
                    actor: $actor,
                    action: PlatformAuditAction::DEVICE_REACTIVATED,
                    targetType: PlatformAuditAction::TARGET_DEVICE,
                    targetId: $device->id,
                    targetLabel: $device->name,
                    businessId: $device->business_id,
                    before: $before,
                    after: $after,
                    metadata: [
                        'device_identifier' => $device->identifier,
                    ],
                );
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Perangkat "'.$device->name.'" berhasil diaktifkan kembali.');
    }
}
