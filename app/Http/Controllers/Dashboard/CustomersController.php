<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardCustomersData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CustomersController extends Controller
{
    public function __construct(
        protected DashboardCustomersData $customersData
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

        // Strip invalid parameters so malformed input falls back safely (no 500).
        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->customersData->get($currentBusiness, $filters);

        return view('customers.index', [
            'customers' => $data['customers'],
            'summary' => $data['summary'],
            'filterOptions' => $data['filterOptions'],
            'currentFilters' => $data['currentFilters'],
            'hasAnyCustomers' => $data['hasAnyCustomers'],
        ]);
    }

    public function detail(Request $request, int $customerId): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(404);
        }

        return response()->json($this->customersData->detail($currentBusiness, $customerId));
    }
}
