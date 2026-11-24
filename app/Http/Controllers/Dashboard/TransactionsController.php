<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Authorization\BusinessPermission;
use App\Services\Catalog\OutletPricing;
use App\Services\Dashboard\DashboardTransactionsData;
use App\Services\Pos\PosCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionsController extends Controller
{
    public function __construct(
        protected DashboardTransactionsData $transactionsData,
        protected PosCheckoutService $checkoutService,
        protected OutletPricing $outletPricing = new OutletPricing
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        $role = $request->attributes->get('dashboard_business_role');
        $permissions = $request->attributes->get('dashboard_business_permissions', []);

        $canEdit = $currentBusiness !== null && (
            $role === Business::ROLE_OWNER
            || in_array('*', $permissions, true)
            || in_array(BusinessPermission::TRANSACTIONS_MANAGE, $permissions, true)
        );

        $canAddItems = $canEdit || (
            $currentBusiness !== null && in_array(BusinessPermission::POS_SELL, $permissions, true)
        );

        $filters = $request->only([
            'q',
            'date',
            'start_date',
            'end_date',
            'outlet_id',
            'payment_method',
            'payment_status',
            'status',
        ]);

        // Default outlet filter to active branch from sidebar context if not explicitly passed
        if (! $request->has('outlet_id')) {
            $activeOutletId = session('dashboard.active_outlet_id');
            if ($activeOutletId) {
                $filters['outlet_id'] = (int) $activeOutletId;
            }
        }

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7days', '30days', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);

        // If validation fails on any field (e.g. malformed date or bad input),
        // strip the invalid parameter so the request falls back safely without 500
        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->transactionsData->get($currentBusiness, $filters);

        $paymentMethods = $currentBusiness
            ? app(\App\Services\Payment\PaymentMethodService::class)->ensureDefaults($currentBusiness)
            : collect();

        return view('transactions.index', [
            'transactions' => $data['transactions'],
            'metrics' => $data['metrics'],
            'filterOptions' => $data['filterOptions'],
            'currentFilters' => $data['currentFilters'],
            'hasAnyTransactions' => $data['hasAnyTransactions'],
            'canEdit' => $canEdit,
            'canAddItems' => $canAddItems,
            'paymentMethods' => $paymentMethods,
            'taxEnabled' => (bool) ($currentBusiness?->tax_enabled ?? false),
            'taxRate' => (float) ($currentBusiness?->tax_rate ?? 0),
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        if ($currentBusiness === null || (int) $sale->business_id !== (int) $currentBusiness->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_status' => ['required', 'string', Rule::in(['paid', 'unpaid', 'refund', 'refunded', 'Lunas', 'Belum Lunas', 'Refund'])],
            'status' => ['required', 'string', Rule::in(['completed', 'cancelled', 'canceled', 'processing', 'siap', 'proses', 'batal', 'Selesai', 'Dibatalkan', 'Siap', 'Proses', 'Batal'])],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $canonicalPaymentStatus = match (strtolower(trim($validated['payment_status']))) {
            'lunas', 'paid' => 'paid',
            'belum lunas', 'unpaid' => 'unpaid',
            'refund', 'refunded' => 'refund',
            default => $validated['payment_status'],
        };

        $canonicalStatus = match (strtolower(trim($validated['status']))) {
            'batal', 'dibatalkan', 'cancelled', 'canceled' => 'cancelled',
            'proses', 'processing', 'diproses' => 'processing',
            'siap', 'selesai', 'completed', 'ready' => 'completed',
            default => $validated['status'],
        };

        $canonicalOrderStatus = match ($canonicalStatus) {
            'cancelled' => 'Batal',
            'processing' => 'Proses',
            'completed' => 'Siap',
            default => 'Siap',
        };

        // When a transaction is cancelled, automatically mark payment status as refund as well
        if ($canonicalStatus === 'cancelled') {
            $canonicalPaymentStatus = 'refund';
            $canonicalOrderStatus = 'Batal';
        }

        $rawMethod = strtolower(trim($validated['payment_method']));
        $canonicalPaymentMethod = match ($rawMethod) {
            'cash', 'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card', 'kartu' => 'Kartu',
            'debit' => 'Debit',
            default => ucwords($validated['payment_method']),
        };

        $cashReceived = null;
        $changeAmount = null;
        if (in_array($canonicalPaymentMethod, ['Tunai', 'cash'], true)) {
            if (isset($validated['cash_received']) && $validated['cash_received'] !== null && $validated['cash_received'] !== '') {
                $cashReceived = (int) round((float) $validated['cash_received']);
                $changeAmount = max(0, $cashReceived - (int) $sale->total_amount);
            } else {
                $cashReceived = (int) $sale->total_amount;
                $changeAmount = 0;
            }
        }

        $paidAt = $sale->paid_at;
        if ($canonicalPaymentStatus === 'paid' && $paidAt === null) {
            $paidAt = now();
        } elseif ($canonicalPaymentStatus === 'unpaid') {
            $paidAt = null;
        }

        $sale->update([
            'payment_method' => $canonicalPaymentMethod,
            'payment_status' => $canonicalPaymentStatus,
            'status' => $canonicalStatus,
            'order_status' => $canonicalOrderStatus,
            'paid_at' => $paidAt,
            'cash_received' => $cashReceived,
            'change_amount' => $changeAmount,
            'note' => array_key_exists('note', $validated) ? $validated['note'] : $sale->note,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi '.$sale->transaction_number.' berhasil diperbarui.',
                'sale' => $sale,
                'data' => [
                    'id' => $sale->id,
                    'transaction_number' => $sale->transaction_number,
                    'payment_method' => $canonicalPaymentMethod,
                    'payment_status' => match ($canonicalPaymentStatus) {
                        'paid' => 'Lunas',
                        'unpaid' => 'Belum Lunas',
                        'refund' => 'Refund',
                        default => $canonicalPaymentStatus,
                    },
                    'payment_status_raw' => $canonicalPaymentStatus,
                    'status' => match ($canonicalStatus) {
                        'completed' => 'Siap',
                        'processing' => 'Proses',
                        'cancelled' => 'Batal',
                        default => $canonicalStatus,
                    },
                    'status_raw' => $canonicalStatus,
                    'order_status' => $canonicalOrderStatus,
                    'order_status_raw' => $canonicalOrderStatus,
                    'paid_at' => $sale->paid_at ? \Illuminate\Support\Carbon::parse($sale->paid_at)->setTimezone('Asia/Jakarta')->format('d M Y, H:i') : null,
                    'cash_received' => $cashReceived,
                    'change_amount' => $changeAmount,
                    'note' => $sale->note,
                ],
            ]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaksi '.$sale->transaction_number.' berhasil diperbarui.');
    }

    /**
     * Return active sellable products (with branch pricing and variants) that can be added to a processing sale.
     */
    public function addableProducts(Request $request, Sale $sale): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        if ($currentBusiness === null || (int) $sale->business_id !== (int) $currentBusiness->id) {
            abort(404);
        }

        $outlet = Outlet::query()
            ->where('business_id', $currentBusiness->id)
            ->where('id', (int) $sale->outlet_id)
            ->first();

        $products = Product::query()
            ->where('business_id', $currentBusiness->id)
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->with([
                'category:id,name',
                'outletOverrides' => fn ($q) => $q->where('outlet_id', (int) $sale->outlet_id),
                'variantGroups' => fn ($q) => $q->where('status', '!=', 'deleted')->orderBy('sort_order')->with([
                    'options' => fn ($oq) => $oq->where('status', '!=', 'deleted')->orderBy('sort_order'),
                ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($outlet) {
                $pricing = $outlet instanceof Outlet
                    ? $this->outletPricing->forProduct($product, $outlet)
                    : [
                        'price' => (int) ($product->promo_price ?? $product->price),
                        'base_price' => (int) $product->price,
                        'is_promo' => $product->promo_price !== null && (int) $product->promo_price < (int) $product->price,
                        'is_available' => true,
                    ];

                if (! $pricing['is_available']) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku ?? '',
                    'category' => $product->category?->name ?? 'Tanpa Kategori',
                    'price' => (int) $pricing['price'],
                    'base_price' => (int) ($pricing['base_price'] ?? $product->price),
                    'is_promo' => (bool) ($pricing['is_promo'] ?? false),
                    'stock' => (float) $product->stock,
                    'kind' => (string) ($product->kind ?? 'product'),
                    'unit' => (string) ($product->unit ?? 'pcs'),
                    'variant_groups' => $product->variantGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'is_required' => (bool) $group->is_required,
                        'selection_type' => $group->selection_type ?? 'single',
                        'options' => $group->options->map(fn ($opt) => [
                            'id' => $opt->id,
                            'name' => $opt->name,
                            'price_adjustment' => (int) $opt->price_adjustment,
                        ])->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'products' => $products,
            'tax_enabled' => (bool) $currentBusiness->tax_enabled,
            'tax_rate' => (float) ($currentBusiness->tax_rate ?? 0),
        ]);
    }

    /**
     * Add additional menu items to an order that is still in "Proses" status.
     */
    public function addItems(Request $request, Sale $sale): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        if ($currentBusiness === null || (int) $sale->business_id !== (int) $currentBusiness->id) {
            abort(404);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.variant_option_ids' => ['nullable', 'array'],
            'items.*.variant_option_ids.*' => ['integer'],
            'mark_unpaid' => ['nullable', 'boolean'],
        ]);

        $updatedSale = $this->checkoutService->addItemsToProcessingSale(
            business: $currentBusiness,
            sale: $sale,
            itemsData: $validated['items'],
            user: $request->user(),
            markUnpaid: array_key_exists('mark_unpaid', $validated) ? (bool) $validated['mark_unpaid'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Tambahan menu berhasil disimpan ke pesanan '.$updatedSale->transaction_number.'.',
            'sale' => $updatedSale,
            'data' => [
                'id' => $updatedSale->id,
                'transaction_number' => $updatedSale->transaction_number,
                'subtotal' => (int) $updatedSale->subtotal,
                'discount_amount' => (int) $updatedSale->discount_amount,
                'tax_amount' => (int) $updatedSale->tax_amount,
                'total_amount' => (int) $updatedSale->total_amount,
                'gross_profit' => (float) $updatedSale->gross_profit,
                'payment_status_raw' => $updatedSale->payment_status,
                'payment_status' => match (strtolower((string) $updatedSale->payment_status)) {
                    'paid', 'lunas' => 'Lunas',
                    'unpaid', 'belum lunas' => 'Belum Lunas',
                    'refund', 'refunded' => 'Refund',
                    default => (string) $updatedSale->payment_status,
                },
                'cash_received' => $updatedSale->cash_received,
                'change_amount' => $updatedSale->change_amount,
                'items' => $updatedSale->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'variant_summary' => $item->variant_summary,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'kind' => $item->kind,
                    'pricing_unit' => $item->pricing_unit,
                    'line_total' => $item->line_total,
                    'cost_snapshot' => $item->cost_snapshot,
                    'line_cost' => $item->line_cost,
                ])->values()->all(),
            ],
        ]);
    }
}
