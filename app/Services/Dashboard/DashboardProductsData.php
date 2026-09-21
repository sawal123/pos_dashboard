<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardProductsData
{
    private const PER_PAGE = 25;

    /**
     * Determine stock status consistently across products and stock modules.
     */
    public static function determineStockStatus(float|int|string $stock, float|int|string $minStock): string
    {
        $s = (float) $stock;
        $ms = (float) $minStock;

        if ($s < 0) {
            return 'negative';
        }
        if ($s == 0) {
            return 'empty';
        }
        if ($s <= $ms) {
            return 'low';
        }

        return 'safe';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $business, array $filters): array
    {
        if ($business === null) {
            return $this->emptyResult($filters);
        }

        $activeTab = isset($filters['tab']) && in_array($filters['tab'], ['products', 'services', 'categories'], true)
            ? (string) $filters['tab']
            : 'products';

        // Summary metrics from current full non-deleted catalog of current business
        $totalProducts = Product::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->count();

        $totalServices = Product::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'service')
            ->count();

        $activeCategories = Category::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->count();

        $activeItems = Product::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->whereIn('kind', ['product', 'service'])
            ->count();

        $totalCategories = Category::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->count();

        $summary = [
            'total_products' => $totalProducts,
            'total_services' => $totalServices,
            'active_categories' => $activeCategories,
            'active_items' => $activeItems,
        ];

        $tabCounts = [
            'products' => $totalProducts,
            'services' => $totalServices,
            'categories' => $totalCategories,
        ];

        // Process active tab dataset
        if ($activeTab === 'services') {
            $data = $this->getServicesData($business, $filters);
        } elseif ($activeTab === 'categories') {
            $data = $this->getCategoriesData($business, $filters);
        } else {
            $data = $this->getProductsData($business, $filters);
        }

        $filterOptions = $this->buildFilterOptions($business, $activeTab);

        return [
            'summary' => $summary,
            'tabCounts' => $tabCounts,
            'activeTab' => $activeTab,
            'items' => $data['items'],
            'hasAnyData' => $data['hasAnyData'],
            'categories' => $filterOptions['categories'],
            'statuses' => $filterOptions['statuses'],
            'filters' => $this->sanitizeFilters($filters, $activeTab),
            'filterOptions' => $filterOptions,
            'currentFilters' => $this->sanitizeFilters($filters, $activeTab),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, hasAnyData: bool}
     */
    private function getProductsData(Business $business, array $filters): array
    {
        $baseQuery = Product::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->with('category');

        $hasAnyData = (clone $baseQuery)->exists();

        $query = clone $baseQuery;

        // Search: name, sku, barcode
        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%');
            });
        }

        // Category filter
        $categoryId = isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== 'all'
            ? (int) $filters['category_id']
            : null;
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        // Status filter
        $status = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? (string) $filters['status']
            : null;
        if ($status !== null) {
            $query->where('status', $status);
        }

        // Stock status filter
        $stockStatus = isset($filters['stock_status']) && in_array($filters['stock_status'], ['safe', 'low', 'empty', 'negative'], true)
            ? (string) $filters['stock_status']
            : null;
        if ($stockStatus !== null) {
            match ($stockStatus) {
                'negative' => $query->where('stock', '<', 0),
                'empty' => $query->where('stock', '=', 0),
                'low' => $query->where('stock', '>', 0)->where('stock', '<=', DB::raw('min_stock')),
                'safe' => $query->where('stock', '>', DB::raw('min_stock')),
            };
        }

        /** @var LengthAwarePaginator<int, Product> $paginator */
        $paginator = $query->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();

        $mapped = $paginator->through(fn (Product $p) => $this->presentProduct($p));

        return [
            'items' => $mapped,
            'hasAnyData' => $hasAnyData,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, hasAnyData: bool}
     */
    private function getServicesData(Business $business, array $filters): array
    {
        $baseQuery = Product::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'service')
            ->with('category');

        $hasAnyData = (clone $baseQuery)->exists();

        $query = clone $baseQuery;

        // Search: name, sku
        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%');
            });
        }

        // Category filter
        $categoryId = isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== 'all'
            ? (int) $filters['category_id']
            : null;
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        // Status filter
        $status = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? (string) $filters['status']
            : null;
        if ($status !== null) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator<int, Product> $paginator */
        $paginator = $query->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();

        $mapped = $paginator->through(fn (Product $p) => $this->presentService($p));

        return [
            'items' => $mapped,
            'hasAnyData' => $hasAnyData,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, hasAnyData: bool}
     */
    private function getCategoriesData(Business $business, array $filters): array
    {
        $baseQuery = Category::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->withCount(['products' => function (Builder $q): void {
                $q->where('status', '!=', 'deleted');
            }]);

        $hasAnyData = (clone $baseQuery)->exists();

        $query = clone $baseQuery;

        // Search: name
        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }

        // Status filter
        $status = isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
            ? (string) $filters['status']
            : null;
        if ($status !== null) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator<int, Category> $paginator */
        $paginator = $query->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();

        $mapped = $paginator->through(fn (Category $c) => $this->presentCategory($c));

        return [
            'items' => $mapped,
            'hasAnyData' => $hasAnyData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProduct(Product $product): array
    {
        $categoryName = ($product->category && $product->category->status !== 'deleted')
            ? $product->category->name
            : 'Tanpa Kategori';

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,

            'category_id' => $product->category_id,
            'category_name' => $categoryName,

            'price' => (int) $product->price,
            'cost' => (string) $product->cost,

            'stock' => (string) $product->stock,
            'unit' => $product->unit ?? 'pcs',
            'min_stock' => (string) $product->min_stock,
            'stock_status' => self::determineStockStatus($product->stock, $product->min_stock),

            'status_raw' => $product->status,
            'status' => $this->presentStatus($product->status),

            'kind' => 'product',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentService(Product $service): array
    {
        $categoryName = ($service->category && $service->category->status !== 'deleted')
            ? $service->category->name
            : 'Tanpa Kategori';

        return [
            'id' => $service->id,
            'name' => $service->name,
            'sku' => $service->sku,

            'category_id' => $service->category_id,
            'category_name' => $categoryName,

            'price' => (int) $service->price,

            'pricing_unit' => $service->pricing_unit ?: 'paket',
            'min_quantity' => (string) $service->min_quantity,
            'unit' => $service->unit ?: '',
            'estimated_duration' => $service->estimated_duration,

            'status_raw' => $service->status,
            'status' => $this->presentStatus($service->status),

            'kind' => 'service',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'status_raw' => $category->status,
            'status' => $this->presentStatus($category->status),
            'items_count' => (int) ($category->products_count ?? 0),
        ];
    }

    /**
     * Present raw DB status to human-readable label.
     */
    public function presentStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilterOptions(Business $business, string $activeTab): array
    {
        // Category options: current business non-deleted categories only
        $categories = Category::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->toArray();

        // Status options: distinct from current business non-deleted records of active tab
        if ($activeTab === 'categories') {
            $rawStatuses = Category::query()
                ->where('business_id', $business->id)
                ->where('status', '!=', 'deleted')
                ->distinct()
                ->orderBy('status')
                ->pluck('status');
        } else {
            $kind = $activeTab === 'services' ? 'service' : 'product';
            $rawStatuses = Product::query()
                ->where('business_id', $business->id)
                ->where('status', '!=', 'deleted')
                ->where('kind', $kind)
                ->distinct()
                ->orderBy('status')
                ->pluck('status');
        }

        $statuses = $rawStatuses->map(fn (string $s) => [
            'value' => $s,
            'label' => $this->presentStatus($s),
        ])->values()->toArray();

        return [
            'categories' => $categories,
            'statuses' => $statuses,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters, string $activeTab): array
    {
        return [
            'tab' => $activeTab,
            'q' => isset($filters['q']) ? (string) $filters['q'] : '',
            'category_id' => isset($filters['category_id']) ? (string) $filters['category_id'] : '',
            'status' => isset($filters['status']) ? (string) $filters['status'] : '',
            'stock_status' => isset($filters['stock_status']) ? (string) $filters['stock_status'] : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function emptyResult(array $filters): array
    {
        $activeTab = isset($filters['tab']) && in_array($filters['tab'], ['products', 'services', 'categories'], true)
            ? (string) $filters['tab']
            : 'products';

        $page = LengthAwarePaginator::resolveCurrentPage();
        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        return [
            'summary' => [
                'total_products' => 0,
                'total_services' => 0,
                'active_categories' => 0,
                'active_items' => 0,
            ],
            'tabCounts' => [
                'products' => 0,
                'services' => 0,
                'categories' => 0,
            ],
            'activeTab' => $activeTab,
            'items' => $empty,
            'hasAnyData' => false,
            'categories' => [],
            'statuses' => [],
            'filters' => $this->sanitizeFilters($filters, $activeTab),
            'filterOptions' => [
                'categories' => [],
                'statuses' => [],
            ],
            'currentFilters' => $this->sanitizeFilters($filters, $activeTab),
        ];
    }
}
