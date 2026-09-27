<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for activating/deactivating a catalog record
 * (product, service or category) — DASH-15.
 *
 * Hard deletion is not exposed: deactivation writes `status = inactive`, a
 * value the sync protocol already understands. The dashboard never writes the
 * `deleted` tombstone itself; that stays a sync-client responsibility.
 */
class UpdateCatalogStatusRequest extends FormRequest
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

        $productId = $this->route('productId');
        if ($productId !== null) {
            $owned = Product::query()
                ->where('business_id', $business->id)
                ->whereKey($productId)
                ->exists();

            if (! $owned) {
                abort(404);
            }

            return true;
        }

        $categoryId = $this->route('categoryId');
        if ($categoryId !== null) {
            $owned = Category::query()
                ->where('business_id', $business->id)
                ->whereKey($categoryId)
                ->exists();

            if (! $owned) {
                abort(404);
            }
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(CatalogManagementService::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status hanya dapat Aktif atau Nonaktif.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
