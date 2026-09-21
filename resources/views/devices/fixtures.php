<?php

return function (): array {
    if (! app()->environment('local', 'testing')) {
        return [
            'summary' => [
                'total_devices' => 0,
                'active_devices' => 0,
                'inactive_devices' => 0,
                'never_seen_devices' => 0,
            ],
            'outlets' => [],
            'platforms' => [],
            'devices' => [],
        ];
    }

    $devices = [
        [
            'id' => 1,
            'business_id' => 1,
            'outlet_id' => 1,
            'name' => 'Kasir Utama',
            'identifier' => 'device-android-kasir-utama-001',
            'platform' => 'Android',
            'status' => 'active',
            'registered_at_raw' => '2026-09-15 08:00:00',
            'registered_at' => '15 Sep 2026 · 08:00',
            'last_seen_at_raw' => '2026-09-21 10:42:00',
            'last_seen_at' => '21 Sep 2026 · 10:42',
            'notes' => 'Tablet kasir counter utama',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 2,
            'business_id' => 1,
            'outlet_id' => 1,
            'name' => 'Bar Order Terminal',
            'identifier' => 'device-ios-bar-order-002',
            'platform' => 'iOS',
            'status' => 'active',
            'registered_at_raw' => '2026-09-16 09:30:00',
            'registered_at' => '16 Sep 2026 · 09:30',
            'last_seen_at_raw' => '2026-09-21 11:15:00',
            'last_seen_at' => '21 Sep 2026 · 11:15',
            'notes' => 'iPad barista penerima order',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 3,
            'business_id' => 1,
            'outlet_id' => 2,
            'name' => 'Kasir Cabang Barat',
            'identifier' => 'device-android-cabang-barat-001',
            'platform' => 'Android',
            'status' => 'active',
            'registered_at_raw' => '2026-09-17 10:00:00',
            'registered_at' => '17 Sep 2026 · 10:00',
            'last_seen_at_raw' => '2026-09-20 16:30:00',
            'last_seen_at' => '20 Sep 2026 · 16:30',
            'notes' => null,
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 4,
            'business_id' => 1,
            'outlet_id' => 1,
            'name' => 'Tablet Waiter Cadangan',
            'identifier' => 'device-pos-waiter-cadangan-003',
            'platform' => null,
            'status' => 'inactive',
            'registered_at_raw' => '2026-09-18 14:00:00',
            'registered_at' => '18 Sep 2026 · 14:00',
            'last_seen_at_raw' => '2026-09-19 12:00:00',
            'last_seen_at' => '19 Sep 2026 · 12:00',
            'notes' => 'Perangkat cadangan saat jam sibuk',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 5,
            'business_id' => 1,
            'outlet_id' => 2,
            'name' => 'Perangkat Baru Terdaftar',
            'identifier' => 'device-android-kitchen-display-004',
            'platform' => 'Android',
            'status' => 'active',
            'registered_at_raw' => '2026-09-21 07:00:00',
            'registered_at' => '21 Sep 2026 · 07:00',
            'last_seen_at_raw' => null,
            'last_seen_at' => null,
            'notes' => 'Display dapur belum melakukan sync pertama',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
    ];

    $totalDevices = count($devices);
    $activeDevices = 0;
    $inactiveDevices = 0;
    $neverSeenDevices = 0;
    $platforms = [];
    $outlets = [];

    foreach ($devices as $d) {
        if ($d['status'] === 'active') {
            $activeDevices++;
        } else {
            $inactiveDevices++;
        }

        if (empty($d['last_seen_at_raw'])) {
            $neverSeenDevices++;
        }

        if (! empty($d['platform']) && ! in_array($d['platform'], $platforms, true)) {
            $platforms[] = $d['platform'];
        }

        if (! empty($d['outlet_name']) && ! in_array($d['outlet_name'], $outlets, true)) {
            $outlets[] = $d['outlet_name'];
        }
    }

    return [
        'summary' => [
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'inactive_devices' => $inactiveDevices,
            'never_seen_devices' => $neverSeenDevices,
        ],
        'outlets' => $outlets,
        'platforms' => $platforms,
        'devices' => $devices,
    ];
};
