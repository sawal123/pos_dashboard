<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for dashboard device pre-registration (DASH-17).
 *
 * The tenant always comes from the shared dashboard context. The outlet must
 * belong to the active business. The identifier is intentionally *not* a
 * straight uniqueness rule: a duplicate on the same outlet is resolved
 * idempotently by the service, and a duplicate on another outlet is rejected
 * with an outlet-mismatch error.
 */
class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        return $user !== null
            && $business !== null
            && app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::DEVICES_MANAGE);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'identifier' => is_string($this->input('identifier')) ? trim((string) $this->input('identifier')) : $this->input('identifier'),
            'platform' => $this->normalizeNullable('platform'),
            'notes' => $this->normalizeNullable('notes'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();

        return [
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:100'],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($query) => $query->where('business_id', $business?->id)),
            ],
            'platform' => ['nullable', 'string', 'max:50'],
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
            'identifier.required' => 'Identifier perangkat wajib diisi.',
            'identifier.max' => 'Identifier perangkat maksimal 100 karakter.',
            'outlet_id.required' => 'Outlet wajib dipilih.',
            'outlet_id.exists' => 'Outlet yang dipilih tidak valid.',
            'platform.max' => 'Platform maksimal 50 karakter.',
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
