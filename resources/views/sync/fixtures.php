<?php

return function (): array {
    if (! app()->environment('local', 'testing')) {
        return [
            'sync_counter' => [
                'business_id' => 1,
                'current_sequence' => 0,
            ],
            'summary' => [
                'server_sequence' => 0,
                'total_requests' => 0,
                'devices_with_push' => 0,
                'last_processed' => 'Belum Ada',
            ],
            'outlets' => [],
            'devices' => [],
            'requests' => [],
        ];
    }

    $requests = [
        [
            'id' => 1,
            'business_id' => 1,
            'device_id' => 1,
            'request_id' => '6d736a0a-552a-4fa5-889c-01b61e430001',
            'processed_at_raw' => '2026-09-21 10:42:00',
            'processed_at' => '21 Sep 2026 · 10:42',
            'device_name' => 'Kasir Utama',
            'device_identifier' => 'device-android-kasir-utama-001',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 2,
            'business_id' => 1,
            'device_id' => 2,
            'request_id' => '8e452f1b-314b-4bc6-9811-02c72f540002',
            'processed_at_raw' => '2026-09-21 11:15:00',
            'processed_at' => '21 Sep 2026 · 11:15',
            'device_name' => 'Bar Order Terminal',
            'device_identifier' => 'device-ios-bar-order-002',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 3,
            'business_id' => 1,
            'device_id' => 3,
            'request_id' => '9f563c2c-425c-4cd7-a922-03d83a650003',
            'processed_at_raw' => '2026-09-20 16:30:00',
            'processed_at' => '20 Sep 2026 · 16:30',
            'device_name' => 'Kasir Cabang Barat',
            'device_identifier' => 'device-android-cabang-barat-001',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 4,
            'business_id' => 1,
            'device_id' => 1,
            'request_id' => 'a0674d3d-536d-4de8-ba33-04e94b760004',
            'processed_at_raw' => '2026-09-19 14:20:00',
            'processed_at' => '19 Sep 2026 · 14:20',
            'device_name' => 'Kasir Utama',
            'device_identifier' => 'device-android-kasir-utama-001',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 5,
            'business_id' => 1,
            'device_id' => 4,
            'request_id' => 'b1785e4e-647e-4ef9-cb44-05fa5c870005',
            'processed_at_raw' => '2026-09-18 12:00:00',
            'processed_at' => '18 Sep 2026 · 12:00',
            'device_name' => 'Tablet Waiter Cadangan',
            'device_identifier' => 'device-pos-waiter-cadangan-003',
            'outlet_name' => 'Outlet Utama',
        ],
    ];

    $counter = [
        'business_id' => 1,
        'current_sequence' => 1284,
    ];

    // Compute summary metrics
    $totalRequests = count($requests);
    $deviceIds = [];
    $maxProcessedRaw = null;
    $maxProcessedDisplay = 'Belum Ada';
    $outlets = [];
    $devices = [];

    foreach ($requests as $r) {
        $deviceIds[$r['device_id']] = true;

        if ($maxProcessedRaw === null || $r['processed_at_raw'] > $maxProcessedRaw) {
            $maxProcessedRaw = $r['processed_at_raw'];
            $maxProcessedDisplay = $r['processed_at'];
        }

        if (! in_array($r['outlet_name'], $outlets, true)) {
            $outlets[] = $r['outlet_name'];
        }

        if (! in_array($r['device_name'], $devices, true)) {
            $devices[] = $r['device_name'];
        }
    }

    return [
        'sync_counter' => $counter,
        'summary' => [
            'server_sequence' => $counter['current_sequence'],
            'total_requests' => $totalRequests,
            'devices_with_push' => count($deviceIds),
            'last_processed' => $maxProcessedDisplay,
        ],
        'outlets' => $outlets,
        'devices' => $devices,
        'requests' => $requests,
    ];
};
