<?php

namespace App\Services\Catalog;

use App\Models\Outlet;
use App\Models\OutletProduct;
use App\Models\Product;
use App\Services\Sync\SyncSequence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * MULTI-OUTLET — resolves effective product pricing & availability per branch.
 *
 * A product always has its master `price` / `promo_price` on `products`. An
 * outlet may override either value (or hide the product) via `outlet_product`;
 * NULL on the override means "follow the master product".
 */
final class OutletPricing
{
    /**
     * Effective catalog values for one product at one outlet.
     *
     * @return array{price: int, promo_price: int|null, is_available: bool, has_override: bool, override_price: int|null, override_promo_price: int|null}
     */
    public function forProduct(Product $product, int|Outlet|null $outlet, ?OutletProduct $preloadedOverride = null): array
    {
        $outletId = $outlet instanceof Outlet ? $outlet->id : $outlet;

        if ($outletId === null) {
            return [
                'price' => (int) $product->price,
                'promo_price' => $product->promo_price !== null ? (int) $product->promo_price : null,
                'is_available' => true,
                'has_override' => false,
                'override_price' => null,
                'override_promo_price' => null,
            ];
        }

        $override = $preloadedOverride;

        if ($override === null) {
            if ($product->relationLoaded('outletOverrides')) {
                /** @var OutletProduct|null $override */
                $override = $product->outletOverrides->firstWhere('outlet_id', $outletId);
            } else {
                /** @var OutletProduct|null $override */
                $override = OutletProduct::query()
                    ->where('outlet_id', $outletId)
                    ->where('product_id', $product->id)
                    ->first();
            }
        }

        $overridePrice = $override?->price !== null ? (int) $override->price : null;
        $overridePromo = $override?->promo_price !== null ? (int) $override->promo_price : null;
        $isAvailable = $override === null ? true : (bool) $override->is_available;

        $effectivePrice = $overridePrice ?? (int) $product->price;

        // If the branch defines its own regular price without a branch promo
        // price, do not leak a master promo_price that might exceed or misprice
        // the branch override unless the master promo is still below the
        // effective price and no branch override was set.
        if ($overridePromo !== null) {
            $effectivePromo = $overridePromo;
        } elseif ($overridePrice !== null) {
            $effectivePromo = null;
        } else {
            $effectivePromo = $product->promo_price !== null ? (int) $product->promo_price : null;
        }

        return [
            'price' => $effectivePrice,
            'promo_price' => $effectivePromo,
            'is_available' => $isAvailable,
            'has_override' => $override !== null && ! $override->isNeutral(),
            'override_price' => $overridePrice,
            'override_promo_price' => $overridePromo,
        ];
    }

    /**
     * Keyed map `product_id => OutletProduct` for a collection of products at one outlet.
     *
     * @param  EloquentCollection<int, Product>|Collection<int, Product>|list<int>  $products
     * @return Collection<int, OutletProduct>
     */
    public function overridesForOutlet(int $outletId, EloquentCollection|Collection|array $products): Collection
    {
        $ids = collect($products)->map(fn ($p) => $p instanceof Product ? $p->id : (int) $p)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return OutletProduct::query()
            ->where('outlet_id', $outletId)
            ->whereIn('product_id', $ids->all())
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Save per-outlet overrides for a product and bump the product's sync
     * sequence so connected mobile devices pull the updated effective price.
     *
     * @param  array<int|string, array{price?: int|string|null, promo_price?: int|string|null, is_available?: bool|int|string|null}>  $byOutletId
     */
    public function syncProductOverrides(Product $product, array $byOutletId): void
    {
        if ($byOutletId === []) {
            return;
        }

        $validOutletIds = Outlet::query()
            ->where('business_id', $product->business_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $validLookup = array_flip($validOutletIds);

        $changed = false;

        foreach ($byOutletId as $rawOutletId => $row) {
            $outletId = (int) $rawOutletId;
            if (! isset($validLookup[$outletId]) || ! is_array($row)) {
                continue;
            }

            $price = isset($row['price']) && $row['price'] !== '' && $row['price'] !== null
                ? max(0, (int) $row['price'])
                : null;
            $promoPrice = isset($row['promo_price']) && $row['promo_price'] !== '' && $row['promo_price'] !== null
                ? max(0, (int) $row['promo_price'])
                : null;
            $isAvailable = ! array_key_exists('is_available', $row) || filter_var($row['is_available'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;

            /** @var OutletProduct|null $existing */
            $existing = OutletProduct::query()
                ->where('outlet_id', $outletId)
                ->where('product_id', $product->id)
                ->first();

            if ($price === null && $promoPrice === null && $isAvailable) {
                if ($existing !== null) {
                    $existing->delete();
                    $changed = true;
                }

                continue;
            }

            if ($existing === null) {
                OutletProduct::query()->create([
                    'business_id' => $product->business_id,
                    'outlet_id' => $outletId,
                    'product_id' => $product->id,
                    'price' => $price,
                    'promo_price' => $promoPrice,
                    'is_available' => $isAvailable,
                ]);
                $changed = true;
            } elseif (
                $existing->price !== $price
                || $existing->promo_price !== $promoPrice
                || (bool) $existing->is_available !== $isAvailable
            ) {
                $existing->forceFill([
                    'price' => $price,
                    'promo_price' => $promoPrice,
                    'is_available' => $isAvailable,
                ])->save();
                $changed = true;
            }
        }

        if ($changed) {
            $product->forceFill([
                'sync_version' => ((int) $product->sync_version) + 1,
                'sync_sequence' => SyncSequence::next((int) $product->business_id),
            ])->saveQuietly();
        }
    }
}
