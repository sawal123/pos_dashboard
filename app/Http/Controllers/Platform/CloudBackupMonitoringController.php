<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformCloudBackupMonitoringData;
use Illuminate\View\View;

class CloudBackupMonitoringController extends Controller
{
    public function __construct(
        protected PlatformCloudBackupMonitoringData $backupData,
    ) {}

    /**
     * Display the cloud backup readiness, capability status, and telemetry availability matrix.
     */
    public function index(): View
    {
        $data = $this->backupData->get();

        return view('platform.backups.index', $data);
    }
}
