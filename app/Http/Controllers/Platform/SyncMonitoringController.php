<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Platform\PlatformSyncMonitoringData;
use App\Support\PlatformAuditAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SyncMonitoringController extends Controller
{
    public function __construct(
        protected PlatformSyncMonitoringData $syncData
    ) {}

    /**
     * Display a listing of committed sync requests across all businesses and devices.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'q',
            'business_id',
            'device_id',
            'outlet_id',
            'date',
            'start_date',
            'end_date',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'business_id' => ['nullable', 'integer'],
            'device_id' => ['nullable', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'in:all,today,7days,30days,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->syncData->get($filters);

        return view('platform.sync.index', $data);
    }

    /**
     * Display details of a single committed sync request in read-only mode.
     */
    public function show(SyncRequest $syncRequest): View
    {
        $syncRequest->load(['business.subscription', 'device.outlet']);

        $business = $syncRequest->business;

        // Defensive validation: ensure device belongs to the same business
        $isDeviceValid = $syncRequest->device !== null
            && (int) $syncRequest->device->business_id === (int) $business->id;
        $device = $isDeviceValid ? $syncRequest->device : null;

        // Defensive validation: ensure outlet belongs to the same business
        $isOutletValid = $device !== null
            && $device->outlet !== null
            && (int) $device->outlet->business_id === (int) $business->id;
        $outlet = $isOutletValid ? $device->outlet : null;

        // Server sequence is strictly per-business; fallback 0 if missing
        $serverSequence = (int) (SyncCounter::where('business_id', $business->id)->value('current_sequence') ?? 0);

        $hasCloudAccess = $business->hasCloudAccess();
        $activity = PlatformSyncMonitoringData::getActivityDiagnostic($device?->last_seen_at, $device !== null);

        return view('platform.sync.show', [
            'syncRequest' => $syncRequest,
            'business' => $business,
            'device' => $device,
            'outlet' => $outlet,
            'serverSequence' => $serverSequence,
            'hasCloudAccess' => $hasCloudAccess,
            'activity' => $activity,
        ]);
    }

    /**
     * Correct the monitoring timestamp without changing the immutable
     * business/device/request identity of a committed sync record.
     */
    public function update(
        Request $request,
        SyncRequest $syncRequest,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'processed_at' => ['required', 'date'],
        ]);

        $before = ['processed_at' => $syncRequest->processed_at?->toIso8601String()];
        $syncRequest->update(['processed_at' => $validated['processed_at']]);

        /** @var User $actor */
        $actor = $request->user();
        $auditLogger->record(
            actor: $actor,
            action: PlatformAuditAction::SYNC_REQUEST_UPDATED,
            targetType: PlatformAuditAction::TARGET_SYNC_REQUEST,
            targetId: $syncRequest->id,
            targetLabel: $syncRequest->request_id,
            businessId: $syncRequest->business_id,
            before: $before,
            after: ['processed_at' => $syncRequest->processed_at?->toIso8601String()],
        );

        return redirect()->route('platform.sync.show', $syncRequest)
            ->with('status', 'Waktu proses sync berhasil diperbarui.');
    }

    /**
     * Remove a monitoring record only; synchronized business data is retained.
     */
    public function destroy(
        Request $request,
        SyncRequest $syncRequest,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);

        $syncId = $syncRequest->id;
        $requestId = $syncRequest->request_id;
        $businessId = $syncRequest->business_id;
        $before = [
            'request_id' => $syncRequest->request_id,
            'device_id' => $syncRequest->device_id,
            'processed_at' => $syncRequest->processed_at?->toIso8601String(),
        ];

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($syncRequest, $auditLogger, $actor, $syncId, $requestId, $businessId, $before): void {
            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SYNC_REQUEST_DELETED,
                targetType: PlatformAuditAction::TARGET_SYNC_REQUEST,
                targetId: $syncId,
                targetLabel: $requestId,
                businessId: $businessId,
                before: $before,
                after: null,
                metadata: ['destructive_action' => true, 'business_data_deleted' => false],
            );
            $syncRequest->delete();
        });

        return redirect()->route('platform.sync.index')
            ->with('status', "Catatan sync '{$requestId}' berhasil dihapus.");
    }
}
