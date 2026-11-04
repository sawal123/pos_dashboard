<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\Pos\PosCatalogData;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosShiftService;
use App\Services\Promo\PromoEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected PosCatalogData $catalogData,
        protected PosCheckoutService $checkoutService,
        protected PosShiftService $shiftService,
        protected PromoEvaluator $promoEvaluator
    ) {}

    /**
     * Display the full-screen Cashier Zone (Zona Kasir).
     */
    public function index(Request $request): View
    {
        $business = $this->activeBusiness($request);

        if ($request->has('outlet_id')) {
            $reqOutletId = (int) $request->query('outlet_id');
            session(['pos.active_outlet_id' => $reqOutletId]);
        }

        $selectedOutletId = session('pos.active_outlet_id');
        $data = $this->catalogData->get($business, $selectedOutletId ? (int) $selectedOutletId : null);

        // Update session with resolved active outlet ID
        if (isset($data['activeOutlet']) && $data['activeOutlet'] instanceof Outlet) {
            session(['pos.active_outlet_id' => $data['activeOutlet']->id]);
        }

        return view('pos.index', $data);
    }

    /**
     * Switch current active POS outlet.
     */
    public function switchOutlet(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        session(['pos.active_outlet_id' => (int) $validated['outlet_id']]);

        return redirect()->route('pos.index');
    }

    /**
     * Open a new shift for the active outlet.
     */
    public function openShift(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'opening_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $outlet = Outlet::query()
            ->where('business_id', $business->id)
            ->where('id', (int) $validated['outlet_id'])
            ->firstOrFail();

        $shift = $this->shiftService->openShift(
            business: $business,
            outlet: $outlet,
            openingCash: (int) $validated['opening_cash'],
            notes: $validated['notes'] ?? null,
            user: $request->user()
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Shift #{$shift->shift_number} berhasil dibuka.",
                'shift' => [
                    'id' => $shift->id,
                    'shift_number' => $shift->shift_number,
                    'status' => $shift->status,
                    'opening_cash' => (int) $shift->opening_cash,
                    'opened_at' => $shift->opened_at->format('d/m/Y H:i'),
                ],
            ]);
        }

        return redirect()->route('pos.index')->with('success', "Shift #{$shift->shift_number} berhasil dibuka.");
    }

    /**
     * Close an active shift.
     */
    public function closeShift(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'closing_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = Shift::query()
            ->where('business_id', $business->id)
            ->where('id', (int) $validated['shift_id'])
            ->firstOrFail();

        $closed = $this->shiftService->closeShift(
            business: $business,
            shift: $shift,
            closingCash: (int) $validated['closing_cash'],
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Shift #{$closed->shift_number} telah berhasil ditutup.",
                'shift' => [
                    'id' => $closed->id,
                    'shift_number' => $closed->shift_number,
                    'status' => $closed->status,
                    'closing_cash' => (int) $closed->closing_cash,
                    'closed_at' => $closed->closed_at?->format('d/m/Y H:i'),
                ],
            ]);
        }

        return redirect()->route('pos.index')->with('success', "Shift #{$closed->shift_number} telah ditutup.");
    }

    /**
     * Get shift financial summary.
     */
    public function shiftSummary(Request $request, int $shiftId): JsonResponse
    {
        $business = $this->activeBusiness($request);

        $shift = Shift::query()
            ->where('business_id', $business->id)
            ->where('id', $shiftId)
            ->firstOrFail();

        $summary = $this->shiftService->shiftSummary($business, $shift);

        return response()->json([
            'success' => true,
            'shift' => [
                'id' => $shift->id,
                'shift_number' => $shift->shift_number,
                'status' => $shift->status,
                'opening_cash' => (int) $shift->opening_cash,
                'opened_at' => $shift->opened_at->format('d/m/Y H:i'),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Preview discount for current cart before checkout.
     */
    public function previewPromo(Request $request): JsonResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'promo_id' => [
                'required',
                'integer',
                Rule::exists('promos', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'subtotal' => ['required', 'integer', 'min:0'],
            'lines' => ['required', 'array'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],
            'lines.*.line_total' => ['required', 'integer', 'min:0'],
        ]);

        /** @var Promo $promo */
        $promo = Promo::query()
            ->where('business_id', $business->id)
            ->where('id', (int) $validated['promo_id'])
            ->with('targets')
            ->firstOrFail();

        $lines = $validated['lines'];
        // Enhance lines with category_id if missing
        $productIds = array_column($lines, 'product_id');
        $productCats = Product::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $productIds)
            ->pluck('category_id', 'id');

        foreach ($lines as &$line) {
            $line['category_id'] = $productCats[$line['product_id']] ?? null;
        }
        unset($line);

        $result = $this->promoEvaluator->evaluate($promo, (int) $validated['subtotal'], $lines, Carbon::now());

        return response()->json($result);
    }

    /**
     * Process checkout for the POS sale.
     */
    public function checkout(Request $request): JsonResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'client_sale_id' => ['required', 'uuid'],
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'shift_id' => [
                'nullable',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'qris', 'transfer', 'debit'])],
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'promo_id' => [
                'nullable',
                'integer',
                Rule::exists('promos', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.variant_option_ids' => ['nullable', 'array'],
            'items.*.variant_option_ids.*' => ['integer'],
            'items.*.variants' => ['nullable', 'array'],
        ]);

        $sale = $this->checkoutService->checkout(
            business: $business,
            payload: $validated,
            user: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil diproses.',
            'sale' => [
                'id' => $sale->id,
                'sync_id' => $sale->sync_id,
                'transaction_number' => $sale->transaction_number,
                'sold_at' => $sale->sold_at->copy()->setTimezone(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i:s'),
                'subtotal' => (int) $sale->subtotal,
                'discount_amount' => (int) $sale->discount_amount,
                'tax_amount' => (int) $sale->tax_amount,
                'total_amount' => (int) $sale->total_amount,
                'payment_method' => $sale->payment_method,
                'cash_received' => (int) $sale->cash_received,
                'change_amount' => (int) $sale->change_amount,
                'gross_profit' => (float) $sale->gross_profit,
                'customer_name' => $sale->customer instanceof Customer ? $sale->customer->name : ($sale->customer_snapshot['name'] ?? null),
                'outlet_name' => $sale->outlet instanceof Outlet ? $sale->outlet->name : 'Outlet',
                'shift_number' => $sale->shift?->shift_number,
                'items_count' => $sale->items->count(),
                'items' => $sale->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'variant_summary' => $item->variant_summary,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => (int) $item->unit_price,
                    'line_total' => (int) $item->line_total,
                ]),
            ],
        ]);
    }

    /**
     * Printable thermal receipt view.
     */
    public function receipt(Request $request, int $saleId): View
    {
        $business = $this->activeBusiness($request);

        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where('id', $saleId)
            ->with(['items', 'promotions.promo', 'customer', 'shift', 'outlet'])
            ->firstOrFail();

        return view('pos.receipt', [
            'business' => $business,
            'sale' => $sale,
        ]);
    }
}
