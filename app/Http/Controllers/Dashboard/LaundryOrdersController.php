<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Outlet;
use App\Services\Dashboard\DashboardLaundryOrdersData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * DASH-11 — read-only laundry order monitoring.
 *
 * The active business always comes from the shared dashboard context, never
 * from a request parameter, and every query is scoped to it by the data
 * service. Nothing on this controller mutates state.
 */
class LaundryOrdersController extends Controller
{
    public function __construct(
        protected DashboardLaundryOrdersData $ordersData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'order_status',
            'payment_status',
            'outlet_id',
            'date',
            'start_date',
            'end_date',
            'overdue',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'order_status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', Rule::in(['all', 'paid', 'unpaid'])],
            'outlet_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7d', '30d', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'overdue' => ['nullable', 'string', Rule::in(['all', 'overdue', 'ontime'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Invalid parameter types are dropped so malformed input never 500s.
        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        // The outlet filter only accepts outlets of the active business.
        if ($currentBusiness !== null && isset($filters['outlet_id'])) {
            $belongsToBusiness = Outlet::query()
                ->where('business_id', $currentBusiness->id)
                ->whereKey((int) $filters['outlet_id'])
                ->exists();

            if (! $belongsToBusiness) {
                unset($filters['outlet_id']);
            }
        }

        $data = $this->ordersData->get($currentBusiness, $filters);

        return view('laundry-orders.index', $data);
    }

    public function detail(Request $request, int $saleId): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(404);
        }

        return response()->json($this->ordersData->detail($currentBusiness, $saleId));
    }
}
