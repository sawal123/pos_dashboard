<?php

namespace App\Services\Dashboard;

use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardStockData
{
    private const PER_PAGE = 25;

    /**
     * Determine stock status consistently across products and stock modules.
     */
    public static function determineStockStatus(float|int|string $stock, float|int|string $minStock): string
    {
        return DashboardProductsData::determineStockStatus($stock, $minStock);
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

        $baseCatalog = Product::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product');

        // Summary metrics across all non-deleted physical products of current business
        $totalItems = (clone $baseCatalog)->count();
        $safeStock = (clone $baseCatalog)->where('stock', '>', DB::raw('min_stock'))->count();
        $lowStock = (clone $baseCatalog)->where('stock', '>', 0)->where('stock', '<=', DB::raw('min_stock'))->count();
        $criticalStock = (clone $baseCatalog)->where('stock', '<=', 0)->count();

        $summary = [
            'total_items' => $totalItems,
            'safe_stock' => $safeStock,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,
            'stock_value' => $this->stockValue($business->id),
            'dead_stock_count' => $this->deadStockCount($business->id),
            'shrinkage_rate' => $this->shrinkageRate($business->id),
            'days_of_cover' => $this->daysOfCover($business->id),
        ];

        $hasAnyStock = (clone $baseCatalog)->exists();

        // Build filtered query
        $query = (clone $baseCatalog)->with('category');

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

        $paginated = $paginator->through(fn (Product $p) => $this->presentStockItem($p));

        $filterOptions = $this->buildFilterOptions($business);

        return [
            'summary' => $summary,
            'items' => $paginated,
            'hasAnyStock' => $hasAnyStock,
            'categories' => $filterOptions['categories'],
            'filters' => $this->sanitizeFilters($filters),
            'filterOptions' => $filterOptions,
            'currentFilters' => $this->sanitizeFilters($filters),
        ];
    }

    /**
     * Fetch stock movements for a specific product of the current business.
     *
     * @return array<string, mixed>
     */
    public function getProductMovements(Business $business, Product $product): array
    {
        $movements = StockMovement::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(51)
            ->get();

        $hasMore = $movements->count() > 50;
        $items = $movements->take(50);

        $mappedMovements = $items->map(function (StockMovement $m): array {
            $carbon = Carbon::parse($m->occurred_at);
            $carbon->setLocale('id');

            return [
                'id' => $m->id,
                'movement_type_raw' => $m->movement_type,
                'movement_type' => $this->presentMovementType($m->movement_type),
                'quantity_change' => (string) $m->quantity_change,
                'stock_before' => (string) $m->stock_before,
                'stock_after' => (string) $m->stock_after,
                'reference_id' => $m->reference_id,
                'category' => $m->category,
                'note' => $m->note,
                'occurred_at' => $carbon->translatedFormat('d M Y · H:i'),
                'occurred_at_raw' => $carbon->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray();

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => (string) $product->stock,
                'min_stock' => (string) $product->min_stock,
                'unit' => $product->unit ?? 'pcs',
                'stock_status' => self::determineStockStatus($product->stock, $product->min_stock),
            ],
            'movements' => $mappedMovements,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Inventory value at the current HPP snapshot.
     */
    private function stockValue(int $businessId): float
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->selectRaw('COALESCE(SUM(stock * cost), 0) as value')
            ->first();

        $row = $product instanceof Product ? $product->toArray() : [];

        return round((float) ($row['value'] ?? 0), 2);
    }

    /**
     * Products with stock on hand but no sale movement in the last 60 days.
     */
    private function deadStockCount(int $businessId): int
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->where('stock', '>', 0)
            ->whereDoesntHave('stockMovements', function (Builder $query): void {
                $query->where('movement_type', StockMovementType::Sale->value)
                    ->where('occurred_at', '>=', now()->subDays(60));
            })
            ->count();
    }

    /**
     * Absolute opname variance divided by the counted system stock, in percent,
     * over posted sessions of the last 90 days.
     */
    private function shrinkageRate(int $businessId): ?float
    {
        $row = (array) DB::table('stock_opname_items')
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->where('stock_opnames.business_id', $businessId)
            ->where('stock_opnames.status', 'posted')
            ->where('stock_opnames.posted_at', '>=', now()->subDays(90))
            ->selectRaw('COALESCE(SUM(ABS(stock_opname_items.variance)), 0) as variance, COALESCE(SUM(stock_opname_items.system_stock), 0) as system')
            ->first();

        $system = (float) ($row['system'] ?? 0);

        if ($system <= 0.0) {
            return null;
        }

        return round(((float) ($row['variance'] ?? 0)) / $system * 100, 2);
    }

    /**
     * Current stock divided by the average daily outflow (sales + usage) of the
     * last 30 days. Null when there is no outflow to extrapolate from.
     */
    private function daysOfCover(int $businessId): ?float
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->selectRaw('COALESCE(SUM(stock), 0) as total_stock')
            ->first();

        $row = $product instanceof Product ? $product->toArray() : [];
        $totalStock = (float) ($row['total_stock'] ?? 0);

        $outflow = (float) StockMovement::query()
            ->where('business_id', $businessId)
            ->whereIn('movement_type', [StockMovementType::Sale->value, StockMovementType::Usage->value])
            ->where('quantity_change', '<', 0)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->sum('quantity_change');

        $dailyOutflow = abs($outflow) / 30;

        if ($dailyOutflow <= 0.0) {
            return null;
        }

        return round($totalStock / $dailyOutflow, 1);
    }

    /**
     * Map a Product model to stock row presentation shape.
     *
     * @return array<string, mixed>
     */
    private function presentStockItem(Product $product): array
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

            'current_stock' => (string) $product->stock,
            'min_stock' => (string) $product->min_stock,
            'unit' => $product->unit ?? 'pcs',
            'stock_status' => self::determineStockStatus($product->stock, $product->min_stock),

            'product_status_raw' => $product->status,
        ];
    }

    /**
     * Map movement type to human-readable label.
     */
    public function presentMovementType(string $raw): string
    {
        return StockMovementType::labelFor($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilterOptions(Business $business): array
    {
        $categories = Category::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->toArray();

        return [
            'categories' => $categories,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'q' => isset($filters['q']) ? (string) $filters['q'] : '',
            'category_id' => isset($filters['category_id']) ? (string) $filters['category_id'] : '',
            'stock_status' => isset($filters['stock_status']) ? (string) $filters['stock_status'] : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function emptyResult(array $filters): array
    {
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
                'total_items' => 0,
                'safe_stock' => 0,
                'low_stock' => 0,
                'critical_stock' => 0,
                'stock_value' => 0.0,
                'dead_stock_count' => 0,
                'shrinkage_rate' => null,
                'days_of_cover' => null,
            ],
            'items' => $empty,
            'hasAnyStock' => false,
            'categories' => [],
            'filters' => $this->sanitizeFilters($filters),
            'filterOptions' => [
                'categories' => [],
            ],
            'currentFilters' => $this->sanitizeFilters($filters),
        ];
    }
}
