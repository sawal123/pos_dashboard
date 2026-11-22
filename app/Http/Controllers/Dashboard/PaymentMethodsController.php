<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Services\Payment\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentMethodsController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected PaymentMethodService $paymentMethodService
    ) {}

    public function index(Request $request): View
    {
        $business = $this->activeBusiness($request);
        $data = $this->paymentMethodService->allWithOutletMatrix($business);

        return view('payment-methods.index', [
            'business' => $business,
            'methods' => $data['methods'],
            'outlets' => $data['outlets'],
            'matrix' => $data['matrix'],
            'categoryLabels' => PaymentMethod::categoryLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $this->paymentMethodService->ensureDefaults($business);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', Rule::in(array_keys(PaymentMethod::categoryLabels()))],
            'is_cash' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'enabled_outlet_ids' => ['nullable', 'array'],
            'enabled_outlet_ids.*' => ['integer'],
        ], [
            'name.required' => 'Nama metode pembayaran wajib diisi.',
        ]);

        $code = $this->paymentMethodService->generateUniqueCode($business, (string) $validated['name']);
        $isCash = $validated['category'] === PaymentMethod::CATEGORY_CASH
            ? true
            : $request->boolean('is_cash');

        $maxOrder = (int) PaymentMethod::where('business_id', $business->id)->max('sort_order');

        $method = PaymentMethod::create([
            'business_id' => $business->id,
            'name' => trim((string) $validated['name']),
            'code' => $code,
            'category' => (string) $validated['category'],
            'is_cash' => $isCash,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => isset($validated['sort_order']) && $validated['sort_order'] !== null
                ? (int) $validated['sort_order']
                : ($maxOrder + 1),
            'description' => ! empty($validated['description']) ? trim((string) $validated['description']) : null,
        ]);

        // Persist per-outlet checkbox selection
        $outlets = $business->outlets()->where('status', 'active')->get();
        $enabledIds = array_map('intval', $validated['enabled_outlet_ids'] ?? []);
        $hasExplicitOutletSelection = $request->has('outlet_selection_present');
        $now = now();

        foreach ($outlets as $outlet) {
            $isEnabled = $hasExplicitOutletSelection
                ? in_array((int) $outlet->id, $enabledIds, true)
                : true;

            DB::table('outlet_payment_method')->updateOrInsert(
                [
                    'outlet_id' => $outlet->id,
                    'payment_method_id' => $method->id,
                ],
                [
                    'business_id' => $business->id,
                    'is_enabled' => $isEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        return redirect()
            ->route('payment-methods.index')
            ->with('status', "Metode pembayaran \"{$method->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        if ((int) $paymentMethod->business_id !== (int) $business->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', Rule::in(array_keys(PaymentMethod::categoryLabels()))],
            'is_cash' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'enabled_outlet_ids' => ['nullable', 'array'],
            'enabled_outlet_ids.*' => ['integer'],
        ]);

        // System 'cash' method always remains is_cash = true
        $isCash = $paymentMethod->code === 'cash'
            ? true
            : ($validated['category'] === PaymentMethod::CATEGORY_CASH ? true : $request->boolean('is_cash'));

        $paymentMethod->update([
            'name' => trim((string) $validated['name']),
            'category' => $paymentMethod->code === 'cash' ? PaymentMethod::CATEGORY_CASH : (string) $validated['category'],
            'is_cash' => $isCash,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $paymentMethod->is_active,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $paymentMethod->sort_order,
            'description' => array_key_exists('description', $validated) && $validated['description'] !== null && trim((string) $validated['description']) !== ''
                ? trim((string) $validated['description'])
                : null,
        ]);

        if ($request->has('outlet_selection_present')) {
            $outlets = $business->outlets()->where('status', 'active')->get();
            $enabledIds = array_map('intval', $validated['enabled_outlet_ids'] ?? []);
            $now = now();

            foreach ($outlets as $outlet) {
                DB::table('outlet_payment_method')->updateOrInsert(
                    [
                        'outlet_id' => $outlet->id,
                        'payment_method_id' => $paymentMethod->id,
                    ],
                    [
                        'business_id' => $business->id,
                        'is_enabled' => in_array((int) $outlet->id, $enabledIds, true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        return redirect()
            ->route('payment-methods.index')
            ->with('status', "Metode pembayaran \"{$paymentMethod->name}\" berhasil diperbarui.");
    }

    public function toggleOutlet(Request $request, PaymentMethod $paymentMethod, Outlet $outlet): JsonResponse|RedirectResponse
    {
        $business = $this->activeBusiness($request);
        if ((int) $paymentMethod->business_id !== (int) $business->id || (int) $outlet->business_id !== (int) $business->id) {
            abort(404);
        }

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $isEnabled = (bool) $validated['is_enabled'];
        $now = now();

        DB::table('outlet_payment_method')->updateOrInsert(
            [
                'outlet_id' => $outlet->id,
                'payment_method_id' => $paymentMethod->id,
            ],
            [
                'business_id' => $business->id,
                'is_enabled' => $isEnabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $msg = $isEnabled
            ? "{$paymentMethod->name} diaktifkan untuk cabang {$outlet->name}."
            : "{$paymentMethod->name} dinonaktifkan untuk cabang {$outlet->name}.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_enabled' => $isEnabled,
                'message' => $msg,
            ]);
        }

        return redirect()->route('payment-methods.index')->with('status', $msg);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        if ((int) $paymentMethod->business_id !== (int) $business->id) {
            abort(404);
        }

        if ($paymentMethod->is_system && $paymentMethod->code === 'cash') {
            return redirect()
                ->route('payment-methods.index')
                ->withErrors(['payment_method' => 'Metode pembayaran utama Tunai (Cash) tidak dapat dihapus.']);
        }

        $name = $paymentMethod->name;
        $paymentMethod->delete();

        return redirect()
            ->route('payment-methods.index')
            ->with('status', "Metode pembayaran \"{$name}\" berhasil dihapus.");
    }
}
