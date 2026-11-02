<?php

namespace App\Services\Pos;

use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use Illuminate\Support\Carbon;

class PosCatalogData
{
    public function __construct(
        protected PosShiftService $shiftService
    ) {}

    /**
     * Gather all catalog and contextual data for the POS interface.
     *
     * @return array<string, mixed>
     */
    public function get(Business $business, ?int $selectedOutletId = null): array
    {
        $outlets = Outlet::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($outlets->isEmpty()) {
            // Fallback or create a default primary outlet if none exists
            $primaryOutlet = Outlet::firstOrCreate(
                ['business_id' => $business->id, 'code' => 'MAIN'],
                ['name' => 'Outlet Utama', 'status' => 'active']
            );
            $outlets = collect([$primaryOutlet]);
        }

        $activeOutlet = null;
        if ($selectedOutletId !== null) {
            $activeOutlet = $outlets->firstWhere('id', $selectedOutletId);
        }
        if (! $activeOutlet instanceof Outlet) {
            $activeOutlet = $outlets->first();
        }

        $currentShift = $this->shiftService->currentOpenShift($business, $activeOutlet);

        $categories = Category::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'barcode',
                'price',
                'cost',
                'stock',
                'min_stock',
                'unit',
                'kind',
                'pricing_unit',
            ])
            ->map(function (Product $product): array {
                return [
                    'id' => (int) $product->id,
                    'category_id' => $product->category_id ? (int) $product->category_id : null,
                    'category_name' => $product->category?->name ?? 'Tanpa Kategori',
                    'name' => (string) $product->name,
                    'sku' => (string) $product->sku,
                    'barcode' => (string) ($product->barcode ?? ''),
                    'price' => (int) $product->price,
                    'cost' => (float) $product->cost,
                    'stock' => (float) $product->stock,
                    'min_stock' => (float) $product->min_stock,
                    'unit' => (string) ($product->unit ?? 'pcs'),
                    'kind' => (string) ($product->kind ?? 'product'),
                    'pricing_unit' => (string) ($product->pricing_unit ?? 'pcs'),
                    'is_low_stock' => $product->kind === 'product' && (float) $product->stock <= (float) $product->min_stock,
                    'is_out_of_stock' => $product->kind === 'product' && (float) $product->stock <= 0,
                ];
            });

        $now = Carbon::now();
        $promos = Promo::query()
            ->where('business_id', $business->id)
            ->where('status', PromoStatus::Active->value)
            ->with('targets')
            ->get()
            ->filter(function (Promo $promo) use ($now): bool {
                if ($promo->starts_at !== null && $now->lt($promo->starts_at)) {
                    return false;
                }
                if ($promo->ends_at !== null && $now->gt($promo->ends_at)) {
                    return false;
                }
                $weekdays = $promo->weekdays;
                if (is_array($weekdays) && $weekdays !== []) {
                    $allowed = array_map(fn ($d) => (int) $d, $weekdays);
                    if (! in_array($now->dayOfWeekIso, $allowed, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function (Promo $promo): array {
                return [
                    'id' => (int) $promo->id,
                    'name' => (string) $promo->name,
                    'code' => (string) ($promo->code ?? ''),
                    'discount_type' => (string) $promo->discount_type,
                    'discount_value' => (int) $promo->discount_value,
                    'scope' => (string) $promo->scope,
                    'min_purchase' => (int) $promo->min_purchase,
                    'max_discount' => $promo->max_discount !== null ? (int) $promo->max_discount : null,
                    'target_ids' => $promo->targets->map(fn ($t) => [
                        'target_id' => (int) $t->target_id,
                        'target_type' => (string) $t->target_type,
                    ])->values()->all(),
                ];
            })
            ->values();

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email'])
            ->map(function (Customer $customer): array {
                return [
                    'id' => (int) $customer->id,
                    'name' => (string) $customer->name,
                    'phone' => (string) ($customer->phone ?? ''),
                    'email' => (string) ($customer->email ?? ''),
                ];
            });

        return [
            'business' => $business,
            'outlets' => $outlets,
            'activeOutlet' => $activeOutlet,
            'currentShift' => $currentShift,
            'categories' => $categories,
            'products' => $products,
            'promos' => $promos,
            'customers' => $customers,
        ];
    }
}
