<?php

namespace App\Services\Dashboard;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read side for the promo dashboard (PROMO-D01).
 */
class DashboardPromosData
{
    public const PER_PAGE = 25;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $business, array $filters = []): array
    {
        $currentFilters = [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'status' => isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
                ? (string) $filters['status']
                : 'all',
        ];

        if ($business === null) {
            return [
                'items' => $this->emptyPaginator(),
                'summary' => $this->emptySummary(),
                'products' => [],
                'categories' => [],
                'currentFilters' => $currentFilters,
                'hasAnyPromo' => false,
            ];
        }

        $businessId = (int) $business->id;

        $summary = [
            'total' => Promo::query()->where('business_id', $businessId)->count(),
            'active' => Promo::query()->where('business_id', $businessId)->where('status', PromoStatus::Active->value)->count(),
            'draft' => Promo::query()->where('business_id', $businessId)->where('status', PromoStatus::Draft->value)->count(),
            'scheduled' => Promo::query()
                ->where('business_id', $businessId)
                ->where('status', PromoStatus::Active->value)
                ->where('starts_at', '>', now())
                ->count(),
        ];

        $query = Promo::query()->where('business_id', $businessId)->with('targets');

        if ($currentFilters['q'] !== '') {
            $q = $currentFilters['q'];
            $query->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }

        if ($currentFilters['status'] !== 'all') {
            $query->where('status', $currentFilters['status']);
        }

        /** @var LengthAwarePaginator<int, Promo> $paginator */
        $paginator = $query->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->through(fn (Promo $promo): array => $this->presentPromo($promo));

        return [
            'items' => $paginator,
            'summary' => $summary,
            'products' => $this->productOptions($businessId),
            'categories' => $this->categoryOptions($businessId),
            'currentFilters' => $currentFilters,
            'hasAnyPromo' => $summary['total'] > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPromo(Promo $promo): array
    {
        $type = PromoDiscountType::tryFrom((string) $promo->discount_type);

        return [
            'id' => (int) $promo->id,
            'name' => (string) $promo->name,
            'code' => $promo->code,
            'discount_type_raw' => (string) $promo->discount_type,
            'discount_type' => $type?->label() ?? (string) $promo->discount_type,
            'discount_value' => (int) $promo->discount_value,
            'discount_label' => $type === PromoDiscountType::Percent
                ? number_format($promo->discount_value / 100, 2, ',', '.').'%'
                : 'Rp '.number_format((int) $promo->discount_value, 0, ',', '.'),
            'scope_raw' => (string) $promo->scope,
            'scope' => PromoScope::tryFrom((string) $promo->scope)?->label() ?? (string) $promo->scope,
            'min_purchase' => (int) $promo->min_purchase,
            'max_discount' => $promo->max_discount,
            'weekdays' => $promo->weekdays ?? [],
            'starts_at_raw' => $promo->starts_at?->format('Y-m-d'),
            'starts_at' => $promo->starts_at?->translatedFormat('d M Y'),
            'ends_at_raw' => $promo->ends_at?->format('Y-m-d'),
            'ends_at' => $promo->ends_at?->translatedFormat('d M Y'),
            'start_time' => $promo->start_time,
            'end_time' => $promo->end_time,
            'usage_limit_total' => $promo->usage_limit_total,
            'priority' => (int) $promo->priority,
            'status_raw' => (string) $promo->status,
            'status' => PromoStatus::tryFrom((string) $promo->status)?->label() ?? (string) $promo->status,
            'targets' => $promo->targets->map(static fn ($target): array => [
                'type' => (string) $target->target_type,
                'id' => (int) $target->target_id,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, sku?: string}>
     */
    private function productOptions(int $businessId): array
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) $product->sku,
            ])->values()->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(int $businessId): array
    {
        return Category::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
            ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return ['total' => 0, 'active' => 0, 'draft' => 0, 'scheduled' => 0];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPaginator(): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: self::PER_PAGE,
            currentPage: LengthAwarePaginator::resolveCurrentPage(),
            options: [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );

        return $empty;
    }
}
