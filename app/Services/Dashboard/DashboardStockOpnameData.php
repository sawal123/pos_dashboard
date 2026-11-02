<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read side for stock opname sessions (INV-D02).
 */
class DashboardStockOpnameData
{
    public const PER_PAGE = 20;

    /**
     * @return array<string, mixed>
     */
    public function get(?Business $business): array
    {
        if ($business === null) {
            return [
                'items' => $this->emptyPaginator(),
                'summary' => $this->emptySummary(),
                'hasAnyOpname' => false,
            ];
        }

        $businessId = (int) $business->id;

        $summary = [
            'total' => StockOpname::query()->where('business_id', $businessId)->count(),
            'draft' => StockOpname::query()->where('business_id', $businessId)->where('status', StockOpname::STATUS_DRAFT)->count(),
            'posted' => StockOpname::query()->where('business_id', $businessId)->where('status', StockOpname::STATUS_POSTED)->count(),
            'variance_items' => StockOpnameItem::query()
                ->where('business_id', $businessId)
                ->where('variance', '!=', 0)
                ->count(),
        ];

        /** @var LengthAwarePaginator<int, StockOpname> $paginator */
        $paginator = StockOpname::query()
            ->where('business_id', $businessId)
            ->withCount(['items', 'items as variance_count' => fn ($query) => $query->where('variance', '!=', 0)])
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->through(fn (StockOpname $opname): array => [
            'id' => (int) $opname->id,
            'code' => (string) $opname->code,
            'status_raw' => (string) $opname->status,
            'status' => $this->presentStatus((string) $opname->status),
            'note' => $opname->note,
            'creator_name' => $opname->creator->name,
            'items_count' => (int) ($opname->items_count ?? 0),
            'variance_count' => (int) ($opname->variance_count ?? 0),
            'created_at' => $this->formatDate($opname->created_at),
            'posted_at' => $this->formatDate($opname->posted_at),
        ]);

        return [
            'items' => $paginator,
            'summary' => $summary,
            'hasAnyOpname' => $summary['total'] > 0,
        ];
    }

    public function find(Business $business, int $opnameId): ?StockOpname
    {
        /** @var StockOpname|null $opname */
        $opname = StockOpname::query()
            ->where('business_id', $business->id)
            ->whereKey($opnameId)
            ->with([
                'items.product:id,business_id,name,sku,unit,stock,min_stock,cost',
                'creator:id,name',
            ])
            ->first();

        return $opname;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentItem(StockOpnameItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'product_name' => $item->product->name,
            'product_sku' => $item->product->sku,
            'unit' => $item->product->unit,
            'system_stock' => (string) $item->system_stock,
            'counted_stock' => $item->counted_stock !== null ? (string) $item->counted_stock : null,
            'variance' => (string) $item->variance,
            'unit_cost' => $item->unit_cost !== null ? (string) $item->unit_cost : null,
        ];
    }

    public function presentStatus(string $raw): string
    {
        return match ($raw) {
            StockOpname::STATUS_DRAFT => 'Draft',
            StockOpname::STATUS_POSTED => 'Diposting',
            StockOpname::STATUS_CANCELLED => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
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

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'draft' => 0,
            'posted' => 0,
            'variance_items' => 0,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = Carbon::instance($value);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y · H:i');
    }
}
