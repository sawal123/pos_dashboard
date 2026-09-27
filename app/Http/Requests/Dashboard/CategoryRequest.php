<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Category;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for creating and updating a category — DASH-15.
 *
 * Names are unique per business (matching the DB constraint); a category id
 * from another tenant resolves to 404 instead of leaking its existence.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        if (! app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::PRODUCTS_MANAGE)) {
            return false;
        }

        if ($this->isUpdate()) {
            $owned = Category::query()
                ->where('business_id', $business->id)
                ->whereKey($this->route('categoryId'))
                ->exists();

            if (! $owned) {
                abort(404);
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();
        $categoryId = $this->isUpdate() ? (int) $this->route('categoryId') : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('business_id', $business?->id)
                    ->ignore($categoryId),
            ],
            'status' => ['sometimes', 'string', Rule::in(CatalogManagementService::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max' => 'Nama kategori maksimal 255 karakter.',
            'name.unique' => 'Nama kategori sudah digunakan pada bisnis ini.',
        ];
    }

    private function isUpdate(): bool
    {
        return $this->route('categoryId') !== null;
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
