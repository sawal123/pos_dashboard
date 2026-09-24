<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardShiftsData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftsController extends Controller
{
    public function __construct(
        protected DashboardShiftsData $shiftsData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'outlet_id',
            'status',
            'date',
            'start_date',
            'end_date',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7d', '30d', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->shiftsData->get($currentBusiness, $filters);

        return view('shifts.index', $data);
    }

    public function detail(Request $request, int $shiftId): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(404);
        }

        $data = $this->shiftsData->detail($currentBusiness, $shiftId);

        return response()->json($data);
    }
}
