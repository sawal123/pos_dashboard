<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardCashData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(
        protected DashboardCashData $cashData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'tab',
            'q',
            'date',
            'start_date',
            'end_date',
            'outlet_id',
            'type',
            'category',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'tab' => ['nullable', 'string', Rule::in(['ledgers', 'expenses'])],
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'string', Rule::in(['all', 'today', '7d', '30d', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(['in', 'out'])],
            'category' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->cashData->get($currentBusiness, $filters);

        return view('cash.index', $data);
    }
}
