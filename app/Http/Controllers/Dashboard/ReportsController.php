<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardReportsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(
        protected DashboardReportsData $reportsData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'date',
            'start_date',
            'end_date',
            'outlet_id',
        ]);

        $validator = Validator::make($filters, [
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7d', '30d', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->reportsData->get($currentBusiness, $filters);

        return view('reports.index', $data);
    }
}
