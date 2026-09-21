<?php

return function (): array {
    if (! app()->environment('local', 'testing')) {
        return [
            'summary' => [
                'total_sales' => 0,
                'total_transactions' => 0,
                'estimated_gross_profit' => '0.00',
                'total_expenses' => 0,
            ],
            'outlets' => [],
            'sales' => [],
            'expenses' => [],
        ];
    }

    $sales = [
        [
            'id' => 1,
            'transaction_number' => 'TRX-260921-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Tunai',
            'total_amount' => 65000,
            'gross_profit' => '32500.00',
            'sold_at_raw' => '2026-09-21 09:30:00',
            'sold_at' => '21 Sep 2026 · 09:30',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 2,
            'transaction_number' => 'TRX-260921-002',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'QRIS',
            'total_amount' => 125000,
            'gross_profit' => '65000.50',
            'sold_at_raw' => '2026-09-21 11:15:00',
            'sold_at' => '21 Sep 2026 · 11:15',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 3,
            'transaction_number' => 'TRX-260921-003',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Transfer',
            'total_amount' => 450000,
            'gross_profit' => '220000.00',
            'sold_at_raw' => '2026-09-21 14:00:00',
            'sold_at' => '21 Sep 2026 · 14:00',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 4,
            'transaction_number' => 'TRX-260920-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'QRIS',
            'total_amount' => 85000,
            'gross_profit' => '42500.00',
            'sold_at_raw' => '2026-09-20 10:20:00',
            'sold_at' => '20 Sep 2026 · 10:20',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 5,
            'transaction_number' => 'TRX-260920-002',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Kartu',
            'total_amount' => 180000,
            'gross_profit' => '90000.00',
            'sold_at_raw' => '2026-09-20 15:45:00',
            'sold_at' => '20 Sep 2026 · 15:45',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 6,
            'transaction_number' => 'TRX-260919-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Tunai',
            'total_amount' => 95000,
            'gross_profit' => '47500.00',
            'sold_at_raw' => '2026-09-19 12:00:00',
            'sold_at' => '19 Sep 2026 · 12:00',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 7,
            'transaction_number' => 'TRX-260918-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'QRIS',
            'total_amount' => 210000,
            'gross_profit' => '105000.00',
            'sold_at_raw' => '2026-09-18 16:10:00',
            'sold_at' => '18 Sep 2026 · 16:10',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 8,
            'transaction_number' => 'TRX-260917-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Tunai',
            'total_amount' => 320000,
            'gross_profit' => '160000.00',
            'sold_at_raw' => '2026-09-17 13:30:00',
            'sold_at' => '17 Sep 2026 · 13:30',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 9,
            'transaction_number' => 'TRX-260916-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'QRIS',
            'total_amount' => 140000,
            'gross_profit' => '70000.00',
            'sold_at_raw' => '2026-09-16 11:20:00',
            'sold_at' => '16 Sep 2026 · 11:20',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 10,
            'transaction_number' => 'TRX-260915-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'Lainnya',
            'total_amount' => 75000,
            'gross_profit' => '37500.00',
            'sold_at_raw' => '2026-09-15 17:00:00',
            'sold_at' => '15 Sep 2026 · 17:00',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
    ];

    $expenses = [
        [
            'id' => 1,
            'description' => 'Pembelian Es Batu Kristal',
            'category' => 'Operasional',
            'amount' => 75000,
            'status' => 'recorded',
            'occurred_at_raw' => '2026-09-21 08:20:00',
            'occurred_at' => '21 Sep 2026 · 08:20',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 2,
            'description' => 'Ongkir Pengantaran Kopi',
            'category' => 'Transportasi',
            'amount' => 50000,
            'status' => 'recorded',
            'occurred_at_raw' => '2026-09-20 13:10:00',
            'occurred_at' => '20 Sep 2026 · 13:10',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 3,
            'description' => 'Sabun Cuci Piring & Microfiber',
            'category' => 'Kebersihan',
            'amount' => 120000,
            'status' => 'recorded',
            'occurred_at_raw' => '2026-09-18 16:30:00',
            'occurred_at' => '18 Sep 2026 · 16:30',
            'outlet_name' => 'Outlet Cabang Barat',
        ],
        [
            'id' => 4,
            'description' => 'Isi Ulang Galon & Gas',
            'category' => 'Bahan Baku',
            'amount' => 250000,
            'status' => 'recorded',
            'occurred_at_raw' => '2026-09-16 11:00:00',
            'occurred_at' => '16 Sep 2026 · 11:00',
            'outlet_name' => 'Outlet Utama',
        ],
        [
            'id' => 5,
            'description' => 'Kemasan Paper Cup 16oz',
            'category' => 'Perlengkapan',
            'amount' => 180000,
            'status' => 'recorded',
            'occurred_at_raw' => '2026-09-15 14:20:00',
            'occurred_at' => '15 Sep 2026 · 14:20',
            'outlet_name' => 'Outlet Utama',
        ],
    ];

    $totalSales = 0;
    $totalGrossProfit = 0.0;
    foreach ($sales as $s) {
        $totalSales += (int) $s['total_amount'];
        $totalGrossProfit += (float) $s['gross_profit'];
    }

    $totalExpenses = 0;
    foreach ($expenses as $e) {
        $totalExpenses += (int) $e['amount'];
    }

    return [
        'summary' => [
            'total_sales' => $totalSales,
            'total_transactions' => count($sales),
            'estimated_gross_profit' => number_format($totalGrossProfit, 2, '.', ''),
            'total_expenses' => $totalExpenses,
        ],
        'outlets' => [
            'Outlet Utama',
            'Outlet Cabang Barat',
        ],
        'sales' => $sales,
        'expenses' => $expenses,
    ];
};
