<?php

namespace App\Services\Inventory;

use App\Enums\CostSource;
use App\Enums\HppMethod;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductRecipeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HPP-D01: Comprehensive HPP (Cost of Goods Sold) and Recipe Service.
 *
 * Calculates HPP via weighted average, last purchase, or recipe bill-of-materials.
 * Respects user's cost_source (manual vs hpp).
 */
final class HppService
{
    /**
     * Apply an incoming stock receipt to recalculate HPP.
     */
    public function applyReceipt(
        Product $product,
        float $quantity,
        float $unitCost,
        float $stockBefore,
        ?string $referenceId = null,
        ?User $user = null,
    ): void {
        $method = HppMethod::tryFrom((string) $product->hpp_method) ?? HppMethod::Average;

        if ($method === HppMethod::None || $method === HppMethod::Recipe) {
            return;
        }

        $newHpp = $unitCost;

        if ($method === HppMethod::Average) {
            $newStock = $stockBefore + $quantity;
            $oldBasis = (float) ($product->hpp !== null && $product->hpp > 0 ? $product->hpp : $product->cost);

            if ($stockBefore > 0 && $newStock > 0) {
                $newHpp = (($stockBefore * $oldBasis) + ($quantity * $unitCost)) / $newStock;
            } else {
                $newHpp = $unitCost;
            }
        }

        $formattedHpp = number_format($newHpp, 2, '.', '');
        $oldHpp = $product->hpp !== null ? (float) $product->hpp : null;

        $product->hpp = $formattedHpp;
        $product->hpp_calculated_at = now();

        $this->recordHistory(
            $product,
            'hpp',
            $oldHpp,
            (float) $formattedHpp,
            'receipt',
            $referenceId,
            $user
        );

        // If user chose to follow HPP automatically, update cost as well
        if ($product->cost_source === CostSource::Hpp->value) {
            $oldCost = (float) $product->cost;
            $product->cost = $formattedHpp;

            $this->recordHistory(
                $product,
                'cost',
                $oldCost,
                (float) $formattedHpp,
                'receipt',
                $referenceId,
                $user
            );
        }

        $product->save();

        // Cascade to any recipe products that use this item
        $this->cascadeRecipeUpdates($product, $user);
    }

    /**
     * Calculate HPP for a recipe-based product.
     */
    public function recalculateRecipe(Product $product, ?User $user = null): float
    {
        $items = $product->recipeItems()->with('componentProduct')->get();

        $baseCost = 0.0;
        foreach ($items as $item) {
            $component = $item->componentProduct;
            if ($component !== null) {
                $compCost = (float) $component->cost;
                $baseCost += ((float) $item->quantity * $compCost);
            }
        }

        $fixedOverhead = (float) $product->hpp_overhead_fixed;
        $percentOverhead = (float) $product->hpp_overhead_percent;

        $totalHpp = $baseCost + $fixedOverhead + ($baseCost * ($percentOverhead / 100));
        $formattedHpp = number_format($totalHpp, 2, '.', '');
        $oldHpp = $product->hpp !== null ? (float) $product->hpp : null;

        $product->hpp = $formattedHpp;
        $product->hpp_calculated_at = now();

        $this->recordHistory(
            $product,
            'hpp',
            $oldHpp,
            (float) $formattedHpp,
            'recipe',
            null,
            $user
        );

        if ($product->cost_source === CostSource::Hpp->value) {
            $oldCost = (float) $product->cost;
            $product->cost = $formattedHpp;

            $this->recordHistory(
                $product,
                'cost',
                $oldCost,
                (float) $formattedHpp,
                'recipe',
                null,
                $user
            );
        }

        $product->save();

        // Cascade upwards if this product is also used in another recipe
        $this->cascadeRecipeUpdates($product, $user);

        return (float) $formattedHpp;
    }

    /**
     * Apply current HPP value to Product cost (one-click action for manual mode).
     */
    public function applyHppToCost(Product $product, ?User $user = null): void
    {
        $hppValue = $product->hpp !== null ? (float) $product->hpp : (float) $product->cost;
        $oldCost = (float) $product->cost;
        $formatted = number_format($hppValue, 2, '.', '');

        $product->cost = $formatted;
        $product->save();

        $this->recordHistory(
            $product,
            'cost',
            $oldCost,
            (float) $formatted,
            'apply_hpp',
            null,
            $user
        );

        $this->cascadeRecipeUpdates($product, $user);
    }

    /**
     * Save recipe components for a product, ensuring no circular dependencies.
     *
     * @param  list<array{component_product_id: int|string, quantity: float|string, note?: string|null}>  $components
     */
    public function syncRecipe(Product $product, array $components, ?User $user = null): void
    {
        $businessId = (int) $product->business_id;

        // Check for self-reference
        foreach ($components as $c) {
            if ((int) $c['component_product_id'] === (int) $product->id) {
                throw ValidationException::withMessages([
                    'recipe' => 'Produk tidak dapat menggunakan dirinya sendiri sebagai bahan resep.',
                ]);
            }
        }

        // Check for circular dependency
        $this->assertNoCircularDependency($product->id, array_column($components, 'component_product_id'));

        DB::transaction(function () use ($product, $components, $businessId, $user): void {
            ProductRecipeItem::query()->where('product_id', $product->id)->delete();

            foreach ($components as $c) {
                $componentId = (int) $c['component_product_id'];
                $qty = (float) $c['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                ProductRecipeItem::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'component_product_id' => $componentId,
                    'quantity' => number_format($qty, 4, '.', ''),
                    'note' => isset($c['note']) ? trim((string) $c['note']) : null,
                ]);
            }

            // Recalculate HPP
            $this->recalculateRecipe($product, $user);
        });
    }

    /**
     * Cascade recipe updates to all parent products that use this item.
     */
    public function cascadeRecipeUpdates(Product $componentProduct, ?User $user = null, int $depth = 0): void
    {
        if ($depth >= 5) {
            return;
        }

        $parentItems = ProductRecipeItem::query()
            ->where('component_product_id', $componentProduct->id)
            ->with('product')
            ->get();

        foreach ($parentItems as $item) {
            $parentProduct = $item->product;
            if ($parentProduct !== null && $parentProduct->hpp_method === HppMethod::Recipe->value) {
                $this->recalculateRecipe($parentProduct, $user);
                $this->cascadeRecipeUpdates($parentProduct, $user, $depth + 1);
            }
        }
    }

    /**
     * Prevent circular dependencies in recipes (e.g. A -> B -> A).
     *
     * @param  list<int|string>  $newComponentIds
     */
    public function assertNoCircularDependency(int $targetProductId, array $newComponentIds): void
    {
        $visited = [];
        $queue = array_map('intval', $newComponentIds);

        while ($queue !== []) {
            $currentId = array_shift($queue);
            if ($currentId === $targetProductId) {
                throw ValidationException::withMessages([
                    'recipe' => 'Terjadi siklus bahan resep (komposisi melingkar) yang tidak diperbolehkan.',
                ]);
            }

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $subComponents = ProductRecipeItem::query()
                ->where('product_id', $currentId)
                ->pluck('component_product_id')
                ->map('intval')
                ->all();

            foreach ($subComponents as $subId) {
                $queue[] = $subId;
            }
        }
    }

    /**
     * Record an entry in the product_cost_histories audit table.
     */
    private function recordHistory(
        Product $product,
        string $field,
        ?float $oldValue,
        float $newValue,
        string $source,
        ?string $referenceId = null,
        ?User $user = null,
    ): void {
        if ($oldValue !== null && abs($oldValue - $newValue) < 0.005) {
            return;
        }

        ProductCostHistory::create([
            'business_id' => $product->business_id,
            'product_id' => $product->id,
            'field' => $field,
            'old_value' => $oldValue !== null ? number_format($oldValue, 2, '.', '') : null,
            'new_value' => number_format($newValue, 2, '.', ''),
            'source' => $source,
            'reference_id' => $referenceId,
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }
}
