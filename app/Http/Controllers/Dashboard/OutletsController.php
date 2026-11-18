<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Outlet;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardOutletsData;
use App\Services\Dashboard\OutletManagementService;
use App\Services\Subscription\MobileSubscriptionPlanCatalog;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutletsController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        protected DashboardOutletsData $outletsData,
        protected OutletManagementService $outlets,
        protected MobileSubscriptionPlanCatalog $planCatalog,
        protected SubscriptionCheckoutService $checkout,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'status',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->outletsData->get($currentBusiness, $filters);
        $data['canManageOutlets'] = app(BusinessAuthorizer::class)
            ->allows($request->user(), $currentBusiness, BusinessPermission::OUTLETS_MANAGE);
        $data['plans'] = $this->planCatalog->plans();
        $data['checkoutAvailable'] = $this->checkout->readiness()['ready'];

        return view('outlets.index', $data);
    }

    public function detail(Request $request, int $outletId): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(404);
        }

        $data = $this->outletsData->detail($currentBusiness, $outletId);

        return response()->json($data);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama cabang wajib diisi.',
            'code.regex' => 'Kode cabang hanya boleh berisi huruf, angka, tanda hubung (-), atau garis bawah (_).',
        ]);

        $outlet = $this->outlets->createBranch($business, $validated);

        return redirect()
            ->route('outlets.index')
            ->with('status', "Cabang \"{$outlet->name}\" berhasil dibuat. Selesaikan pembayaran langganan cabang agar cabang aktif dan dapat digunakan untuk sinkronisasi.")
            ->with('created_outlet_id', $outlet->id);
    }

    public function update(Request $request, int $outletId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $outlet = Outlet::query()
            ->where('business_id', $business->id)
            ->whereKey($outletId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama cabang wajib diisi.',
            'code.regex' => 'Kode cabang hanya boleh berisi huruf, angka, tanda hubung (-), atau garis bawah (_).',
        ]);

        $this->outlets->update($business, $outlet, $validated);

        return redirect()
            ->route('outlets.index')
            ->with('status', 'Informasi cabang diperbarui.');
    }

    public function updateStatus(Request $request, int $outletId): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $outlet = Outlet::query()
            ->where('business_id', $business->id)
            ->whereKey($outletId)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([Outlet::STATUS_ACTIVE, Outlet::STATUS_INACTIVE])],
        ]);

        $this->outlets->updateStatus($business, $outlet, (string) $validated['status']);

        return redirect()
            ->route('outlets.index')
            ->with('status', $validated['status'] === Outlet::STATUS_ACTIVE ? 'Cabang diaktifkan.' : 'Cabang dinonaktifkan.');
    }
}
