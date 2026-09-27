<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Services\Dashboard\Reporting\ReportFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for report exports (DASH-13).
 *
 * Uses the same date/preset rules as the on-screen report, plus an explicit
 * tenant check for `outlet_id`: an outlet that does not belong to the active
 * business is rejected rather than silently swapped for another tenant's data.
 *
 * The tenant is read from the shared dashboard context only — a `business_id`
 * query parameter is never consulted.
 */
class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->activeBusiness() instanceof Business;
    }

    public function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }

    protected function prepareForValidation(): void
    {
        // The filter bar submits `outlet_id=all` for "Semua Outlet".
        $outletId = $this->input('outlet_id');

        if ($outletId === 'all' || $outletId === '') {
            $this->merge(['outlet_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ReportFilters::rules();
        $rules['outlet_id'] = ['nullable', 'integer', $this->outletOwnershipRule()];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.in' => 'Periode laporan tidak valid.',
            'start_date.date_format' => 'Tanggal mulai harus berformat YYYY-MM-DD.',
            'end_date.date_format' => 'Tanggal selesai harus berformat YYYY-MM-DD.',
            'outlet_id.integer' => 'Outlet tidak valid.',
        ];
    }

    private function outletOwnershipRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $business = $this->activeBusiness();

            if ($business === null) {
                return;
            }

            $owned = Outlet::query()
                ->where('business_id', $business->id)
                ->whereKey($value)
                ->exists();

            if (! $owned) {
                $fail('Outlet yang dipilih bukan milik bisnis aktif.');
            }
        };
    }
}
