<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation + authorization for editing a device's safe metadata (DASH-17).
 *
 * Only `name` and `notes` are editable. The identifier and outlet are immutable
 * here; a device owned by another tenant resolves to 404 so its existence is
 * never leaked.
 */
class UpdateDeviceRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'notes' => $this->normalizeNullable('notes'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama perangkat wajib diisi.',
            'name.max' => 'Nama perangkat maksimal 255 karakter.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    private function normalizeNullable(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return is_scalar($value) ? (string) $value : null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
