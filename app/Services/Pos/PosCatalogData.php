<?php

namespace App\Services\Pos;

use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Services\Catalog\OutletPricing;
use Illuminate\Support\Carbon;

class PosCatalogData
{
    public function __construct(
        protected PosShiftService $shiftService,
        protected OutletPricing $outletPricing = new OutletPricing,
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
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
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

        // Attach active open shift to each outlet for modal preview
        $openShifts = \App\Models\Shift::query()
            ->where('business_id', $business->id)
            ->where('status', 'open')
            ->get()
            ->keyBy('outlet_id');

        foreach ($outlets as $out) {
            $out->open_shift = $openShifts->get($out->id);
        }

        $activeOutlet = null;
        if ($selectedOutletId !== null) {
            $activeOutlet = $outlets->firstWhere('id', $selectedOutletId);
        }
        if (! $activeOutlet instanceof Outlet) {
            $activeOutlet = $outlets->firstWhere('is_primary', true) ?? $outlets->first();
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
            ->with([
                'category:id,name',
                'outletOverrides' => fn ($q) => $q->where('outlet_id', $activeOutlet->id),
                'variantGroups' => fn ($q) => $q->where('status', 'active')->with(['activeOptions.ingredientProduct'])->orderBy('sort_order'),
            ])
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'barcode',
                'image_path',
                'price',
                'promo_price',
                'cost',
                'stock',
                'min_stock',
                'unit',
                'kind',
                'pricing_unit',
            ])
            ->filter(function (Product $product) use ($activeOutlet): bool {
                return $this->outletPricing->forProduct($product, $activeOutlet)['is_available'];
            })
            ->map(function (Product $product) use ($activeOutlet): array {
                $pricing = $this->outletPricing->forProduct($product, $activeOutlet);
                $price = $pricing['price'];
                $promoPrice = $pricing['promo_price'];
                $effectivePrice = ($promoPrice !== null && $promoPrice > 0 && $promoPrice < $price) ? $promoPrice : $price;
                $discountPercent = ($effectivePrice < $price && $price > 0)
                    ? (int) round((($price - $effectivePrice) / $price) * 100)
                    : 0;

                $hasVariants = $product->relationLoaded('variantGroups') && $product->variantGroups->isNotEmpty();
                $variantGroups = $hasVariants
                    ? $product->variantGroups->map(fn ($g) => [
                        'id' => (int) $g->id,
                        'name' => (string) $g->name,
                        'selection_type' => (string) $g->selection_type,
                        'is_required' => (bool) $g->is_required,
                        'min_selection' => (int) $g->min_selection,
                        'max_selection' => $g->max_selection !== null ? (int) $g->max_selection : null,
                        'options' => $g->activeOptions->map(fn ($o) => [
                            'id' => (int) $o->id,
                            'name' => (string) $o->name,
                            'price_adjustment' => (int) $o->price_adjustment,
                            'is_default' => (bool) $o->is_default,
                            'has_ingredient' => ! empty($o->ingredient_product_id),
                            'ingredient_name' => $o->ingredientProduct instanceof Product ? $o->ingredientProduct->name : null,
                            'ingredient_quantity' => (float) $o->ingredient_quantity,
                        ])->values()->all(),
                    ])->values()->all()
                    : [];

                return [
                    'id' => (int) $product->id,
                    'category_id' => $product->category_id ? (int) $product->category_id : null,
                    'category_name' => $product->category instanceof Category ? $product->category->name : 'Tanpa Kategori',
                    'name' => (string) $product->name,
                    'sku' => (string) $product->sku,
                    'barcode' => (string) ($product->barcode ?? ''),
                    'image_url' => $product->image_url,
                    'price' => $price,
                    'promo_price' => $promoPrice,
                    'discount_percent' => $discountPercent,
                    'effective_price' => $effectivePrice,
                    'cost' => (float) $product->cost,
                    'stock' => (float) $product->stock,
                    'min_stock' => (float) $product->min_stock,
                    'unit' => (string) ($product->unit ?? 'pcs'),
                    'kind' => (string) ($product->kind ?? 'product'),
                    'pricing_unit' => (string) ($product->pricing_unit ?? 'pcs'),
                    'is_low_stock' => $product->kind === 'product' && (float) $product->stock <= (float) $product->min_stock,
                    'is_out_of_stock' => $product->kind === 'product' && (float) $product->stock <= 0,
                    'has_variants' => $hasVariants,
                    'variant_groups' => $variantGroups,
                ];
            })
            ->values();

        $now = Carbon::now();
        $promos = Promo::query()
            ->where('business_id', $business->id)
            ->where('status', PromoStatus::Active->value)
            ->forOutlet($activeOutlet->id)
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

        $diningTables = [];
        if ($business->table_management_enabled) {
            $diningTables = DiningTable::query()
                ->where('business_id', $business->id)
                ->where(function ($q) use ($activeOutlet) {
                    $q->whereNull('outlet_id');
                    if ($activeOutlet) {
                        $q->orWhere('outlet_id', $activeOutlet->id);
                    }
                })
                ->where('status', '!=', 'inactive')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'capacity', 'status'])
                ->map(fn ($t) => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->name,
                    'capacity' => (int) ($t->capacity ?? 4),
                    'status' => (string) $t->status,
                ])
                ->values()
                ->all();
        }

        $paymentMethods = app(\App\Services\Payment\PaymentMethodService::class)
            ->forOutlet($business, $activeOutlet)
            ->map(fn ($pm) => [
                'id' => (int) $pm->id,
                'name' => (string) $pm->name,
                'code' => (string) $pm->code,
                'category' => (string) $pm->category,
                'is_cash' => (bool) $pm->is_cash,
                'description' => $pm->description,
            ])
            ->values()
            ->all();

        return [
            'business' => $business,
            'isGrosir' => $business->normalizedBusinessType() === 'grosir',
            'tableManagementEnabled' => (bool) $business->table_management_enabled,
            'diningTables' => $diningTables,
            'paymentMethods' => $paymentMethods,
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
