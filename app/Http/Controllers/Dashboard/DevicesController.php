<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\DevicePairingException;
use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreDeviceRequest;
use App\Http\Requests\Dashboard\UpdateDeviceRequest;
use App\Http\Requests\Dashboard\UpdateDeviceStatusRequest;
use App\Models\Business;
use App\Models\Device;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardDevicesData;
use App\Services\Dashboard\DeviceManagementService;
use App\Services\Devices\DevicePairingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class DevicesController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected DashboardDevicesData $devicesData,
        protected DeviceManagementService $devices,
        protected DevicePairingService $pairing,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'outlet_id',
            'status',
            'platform',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->devicesData->get($currentBusiness, $filters);

        $data['canManageDevices'] = app(BusinessAuthorizer::class)
            ->allows($request->user(), $currentBusiness, BusinessPermission::DEVICES_MANAGE);

        // PAIR-01 — kode pairing kasir (4 digit, 30 menit). Only shown when the
        // business can actually pair (cloud plan + manage permission).
        $data['activePairingCodes'] = $currentBusiness instanceof Business
            ? $this->pairing->activeCodes($currentBusiness)
            : collect();

        $data['canPairCashierDevices'] = $data['canManageDevices']
            && $currentBusiness instanceof Business
            && $currentBusiness->hasCloudAccess();

        return view('devices.index', $data);
    }

    // ============================================================
    // DASH-17 — owner-only device registration & management
    // ============================================================

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $result = $this->devices->register($business, $request->validated());

        $message = $result['created']
            ? 'Perangkat berhasil didaftarkan (pra-registrasi). Perangkat akan muncul sebagai terhubung setelah melakukan sinkronisasi.'
            : 'Perangkat dengan identifier ini sudah terdaftar: '.$result['device']->name.'. Tidak ada data yang diubah.';

        return redirect()
            ->route('devices.index')
            ->with('status', $message);
    }

    public function update(UpdateDeviceRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $device = Device::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('deviceId'))
            ->firstOrFail();

        $this->devices->updateMetadata($device, $request->validated());

        return redirect()
            ->route('devices.index')
            ->with('status', 'Metadata perangkat diperbarui.');
    }

    public function updateStatus(UpdateDeviceStatusRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $device = Device::query()
            ->where('business_id', $business->id)
            ->whereKey($request->route('deviceId'))
            ->firstOrFail();

        $status = (string) $request->validated('status');
        $this->devices->updateStatus($device, $status);

        return redirect()
            ->route('devices.index')
            ->with('status', $status === 'active' ? 'Perangkat diaktifkan.' : 'Perangkat dinonaktifkan.');
    }

    // ============================================================
    // PAIR-01 — kode pairing kasir (4 digit, berlaku 30 menit)
    // ============================================================

    public function storePairingCode(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate(
            ['outlet_id' => ['required', 'integer']],
            ['outlet_id.required' => 'Pilih outlet untuk perangkat kasir ini.'],
        );

        try {
            $result = $this->pairing->issue($business, (int) $validated['outlet_id'], $request->user());
        } catch (DevicePairingException $exception) {
            return redirect()
                ->route('devices.index')
                ->withErrors(['pairing_code' => $exception->getMessage()]);
        }

        return redirect()
            ->route('devices.index')
            ->with('status', 'Kode pairing kasir dibuat. Berikan kode ini kepada kasir.')
            ->with('pairing_code', [
                'id' => $result['pairing']->id,
                'code' => $result['code'],
                'outlet' => $result['pairing']->outlet?->name,
                'expires_at' => $result['pairing']->expires_at->toIso8601String(),
            ]);
    }

    public function destroyPairingCode(Request $request, int $codeId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $revoked = $this->pairing->revoke($business, $codeId);

        return redirect()
            ->route('devices.index')
            ->with(
                'status',
                $revoked ? 'Kode pairing dibatalkan.' : 'Kode pairing tidak ditemukan pada bisnis ini.',
            );
    }

    public function updateDeviceLimit(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'max_devices' => ['required', 'integer', 'min:5', 'max:500'],
        ], [
            'max_devices.required' => 'Jumlah batas perangkat harus diisi.',
            'max_devices.min' => 'Batas perangkat minimal 5 perangkat terhubung.',
            'max_devices.integer' => 'Batas perangkat harus berupa angka bulat.',
        ]);

        $business->max_devices = (int) $validated['max_devices'];
        $business->save();

        return redirect()
            ->back()
            ->with('status', "Batas kuota perangkat berhasil diatur menjadi {$business->max_devices} perangkat.");
    }
}
