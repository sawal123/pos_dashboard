<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PromoRequest;
use App\Http\Requests\Dashboard\UpdatePromoStatusRequest;
use App\Models\Business;
use App\Models\Promo;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardPromosData;
use App\Services\Promo\PromoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Promo management (PROMO-D01).
 *
 * Reading uses `promos.view`; every mutation requires `promos.manage`.
 */
class PromosController extends Controller
{
    use ResolvesActiveBusiness;

    public function __construct(
        private readonly DashboardPromosData $promosData,
        private readonly PromoService $promos,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');

        $filters = $request->only(['q', 'status', 'page']);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->promosData->get($business, $filters);
        $data['canManagePromos'] = $business !== null
            && app(BusinessAuthorizer::class)->allows(
                $request->user(),
                $business,
                BusinessPermission::PROMO_MANAGE,
            );

        return view('promos.index', $data);
    }

    public function store(PromoRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $this->promos->create($business, $request->validated());

        return redirect()
            ->route('promos.index')
            ->with('status', 'Promo berhasil dibuat.');
    }

    public function update(PromoRequest $request, int $promoId): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $promo = $this->findPromo($business, $promoId);

        $this->promos->update($promo, $request->validated());

        return redirect()
            ->route('promos.index')
            ->with('status', 'Promo berhasil diperbarui.');
    }

    public function updateStatus(UpdatePromoStatusRequest $request, int $promoId): RedirectResponse
    {
        $business = $this->activeBusiness($request);
        $promo = $this->findPromo($business, $promoId);

        $status = (string) $request->validated('status');
        $this->promos->updateStatus($promo, $status);

        return redirect()
            ->route('promos.index')
            ->with('status', 'Status promo diperbarui.');
    }

    private function findPromo(Business $business, int $promoId): Promo
    {
        /** @var Promo $promo */
        $promo = Promo::query()
            ->where('business_id', $business->id)
            ->whereKey($promoId)
            ->firstOrFail();

        return $promo;
    }
}
