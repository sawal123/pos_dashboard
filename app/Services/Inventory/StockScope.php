<?php

namespace App\Services\Inventory;

use App\Models\Business;
use App\Models\OutletProduct;
use App\Models\Product;

/**
 * MULTI-OUTLET — single read gateway for stock balances.
 *
 * Today every business runs with `stock_scope = shared`, so this returns
 * `products.stock`. When a business is later switched to `per_outlet`, callers
 * already using this helper will read `outlet_product.stock` without further
 * refactoring.
 */
final class StockScope
{
    public function balanceFor(Business $business, Product $product, ?int $outletId = null): float
    {
        if (! $business->stockIsPerOutlet() || $outletId === null) {
            return (float) $product->stock;
        }

        $override = $product->relationLoaded('outletOverrides')
            ? $product->outletOverrides->firstWhere('outlet_id', $outletId)
            : OutletProduct::query()
                ->where('outlet_id', $outletId)
                ->where('product_id', $product->id)
                ->first();

        return (float) ($override?->stock ?? $product->stock);
    }
}
