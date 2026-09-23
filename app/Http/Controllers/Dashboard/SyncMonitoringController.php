<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardSyncData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SyncMonitoringController extends Controller
{
    public function __construct(
        protected DashboardSyncData $syncData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'q',
            'device_id',
            'outlet_id',
            'date',
            'start_date',
            'end_date',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'in:all,today,7days,30days,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->syncData->get($currentBusiness, $filters);

        return view('sync.index', $data);
    }
}
