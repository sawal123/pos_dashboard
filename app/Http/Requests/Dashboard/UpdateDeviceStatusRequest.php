<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DeviceManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for activating/deactivating a device (DASH-17).
 *
 * Only `active`/`inactive` are accepted; devices are never hard-deleted. A
 * device from another tenant resolves to 404.
 */
class UpdateDeviceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        if (! app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::DEVICES_MANAGE)) {
            return false;
        }

        $owned = Device::query()
            ->where('business_id', $business->id)
            ->whereKey($this->route('deviceId'))
            ->exists();

        if (! $owned) {
            abort(404);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(DeviceManagementService::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status perangkat hanya dapat Aktif atau Nonaktif.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
