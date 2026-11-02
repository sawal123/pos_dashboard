<?php

namespace App\Services\Dashboard;

use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read side for the stock ledger history page (INV-D01).
 *
 * `products.stock` stays authoritative; this service only reads the immutable
 * movement rows and their aggregates.
 */
class DashboardStockHistoryData
{
    public const PER_PAGE = 25;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $business, array $filters): array
    {
        $currentFilters = $this->sanitizeFilters($filters);

        if ($business === null) {
            return $this->emptyResult($currentFilters);
        }

        $businessId = (int) $business->id;

        $query = StockMovement::query()->where('stock_movements.business_id', $businessId);
        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, StockMovement> $paginator */
        $paginator = $query
            ->with(['product:id,business_id,name,sku,unit', 'user:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->through(fn (StockMovement $movement): array => $this->presentMovement($movement));

        return [
            'items' => $paginator,
            'summary' => $this->summary($business, $currentFilters),
            'products' => $this->productOptions($businessId),
            'users' => $this->userOptions($businessId),
            'currentFilters' => $currentFilters,
            'hasAnyMovements' => StockMovement::query()
                ->where('business_id', $businessId)
                ->exists(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    public function summary(?Business $business, array $filters): array
    {
        if ($business === null) {
            return $this->emptySummary();
        }

        $businessId = (int) $business->id;
        $currentFilters = $this->sanitizeFilters($filters);

        $query = StockMovement::query()->where('stock_movements.business_id', $businessId);
        $this->applyFilters($query, $currentFilters);

        /** @var StockMovement|null $movement */
        $movement = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) as total_in,'
            .' COALESCE(SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END), 0) as total_out,'
            .' COALESCE(SUM(quantity_change), 0) as net_change,'
            .' COUNT(*) as movement_count'
        )->first();

        $row = $movement instanceof StockMovement ? $movement->toArray() : [];

        $adjustmentQuery = StockMovement::query()->where('stock_movements.business_id', $businessId);
        $this->applyFilters($adjustmentQuery, $currentFilters);

        return [
            'total_in' => round((float) ($row['total_in'] ?? 0), 3),
            'total_out' => round((float) ($row['total_out'] ?? 0), 3),
            'net_change' => round((float) ($row['net_change'] ?? 0), 3),
            'movement_count' => (int) ($row['movement_count'] ?? 0),
            'adjustment_count' => (int) $adjustmentQuery
                ->where('movement_type', StockMovementType::Adjustment->value)
                ->count(),
            'stock_value' => $this->stockValue($businessId),
        ];
    }

    /**
     * @param  Builder<StockMovement>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $query->whereHas('product', function (Builder $sub) use ($q): void {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%');
            });
        }

        $productId = $filters['product_id'] ?? '';
        if ($productId !== '') {
            $query->where('product_id', (int) $productId);
        }

        /** @var list<string> $types */
        $types = $filters['type'] ?? [];
        if ($types !== []) {
            $query->whereIn('movement_type', $types);
        }

        $userId = $filters['user_id'] ?? '';
        if ($userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $from = (string) ($filters['from'] ?? '');
        if ($from !== '') {
            $query->whereDate('occurred_at', '>=', $from);
        }

        $to = (string) ($filters['to'] ?? '');
        if ($to !== '') {
            $query->whereDate('occurred_at', '<=', $to);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMovement(StockMovement $movement): array
    {
        $occurredAt = Carbon::instance($movement->occurred_at);
        $occurredAt->setLocale('id');

        $product = $movement->product;

        return [
            'id' => (int) $movement->id,
            'product_id' => (int) $movement->product_id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit' => $product->unit,
            'movement_type_raw' => (string) $movement->movement_type,
            'movement_type' => StockMovementType::labelFor((string) $movement->movement_type),
            'quantity_change' => (string) $movement->quantity_change,
            'stock_before' => (string) $movement->stock_before,
            'stock_after' => (string) $movement->stock_after,
            'reference_id' => $movement->reference_id,
            'category' => $movement->category,
            'note' => $movement->note,
            'user_name' => $movement->user?->name,
            'occurred_at' => $occurredAt->translatedFormat('d M Y · H:i'),
            'occurred_at_raw' => $occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, sku: string, unit: string}>
     */
    private function productOptions(int $businessId): array
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'deleted')
            ->where('kind', 'product')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) $product->sku,
                'unit' => (string) $product->unit,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function userOptions(int $businessId): array
    {
        return User::query()
            ->whereIn('id', StockMovement::query()
                ->where('business_id', $businessId)
                ->whereNotNull('user_id')
                ->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->values()
            ->all();
    }

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
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        $types = $filters['type'] ?? [];
        if (! is_array($types)) {
            $types = [$types];
        }

        $types = array_values(array_intersect(
            array_map(static fn (mixed $type): string => (string) $type, $types),
            StockMovementType::values(),
        ));

        $from = isset($filters['from']) ? (string) $filters['from'] : '';
        $to = isset($filters['to']) ? (string) $filters['to'] : '';

        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'product_id' => isset($filters['product_id']) && $filters['product_id'] !== ''
                ? (string) (int) $filters['product_id']
                : '',
            'type' => $types,
            'user_id' => isset($filters['user_id']) && $filters['user_id'] !== ''
                ? (string) (int) $filters['user_id']
                : '',
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1 ? $from : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1 ? $to : '',
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptySummary(): array
    {
        return [
            'total_in' => 0.0,
            'total_out' => 0.0,
            'net_change' => 0.0,
            'movement_count' => 0,
            'adjustment_count' => 0,
            'stock_value' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $currentFilters
     * @return array<string, mixed>
     */
    private function emptyResult(array $currentFilters): array
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

        return [
            'items' => $empty,
            'summary' => $this->emptySummary(),
            'products' => [],
            'users' => [],
            'currentFilters' => $currentFilters,
            'hasAnyMovements' => false,
        ];
    }
}
