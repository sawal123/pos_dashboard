<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SyncRequest;
use App\Services\Dashboard\DashboardSyncData;
use Illuminate\Http\JsonResponse;
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

    /**
     * Read-only sync content for a single committed push request, scoped to the
     * active business. Returns the normalized payload (invoices and entity
     * summary) so the detail drawer can render what was actually pushed.
     */
    public function show(Request $request, SyncRequest $syncRequest): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if (! $currentBusiness instanceof Business || (int) $syncRequest->business_id !== (int) $currentBusiness->id) {
            abort(404);
        }

        return response()->json([
            'data' => array_merge([
                'request_id' => (string) $syncRequest->request_id,
            ], $this->syncData->presentSyncContent($syncRequest->payload)),
        ]);
    }
}
