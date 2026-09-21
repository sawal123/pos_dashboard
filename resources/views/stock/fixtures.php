<?php

return function (): array {
    if (! app()->environment('local', 'testing')) {
        return [
            'summary' => [
                'total_items' => 0,
                'safe_stock' => 0,
                'low_stock' => 0,
                'critical_stock' => 0,
            ],
            'categories' => [],
            'items' => [],
        ];
    }

    $items = [
        [
            'id' => 1,
            'name' => 'Biji Kopi Arabika',
            'sku' => 'RAW-001',
            'category_name' => 'Bahan Baku',
            'current_stock' => '2.500',
            'min_stock' => '5.000',
            'unit' => 'kg',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '21 Sep 2026 · 10:15',
                    'movement_type' => 'sale',
                    'movement_type_label' => 'Penjualan',
                    'quantity_change' => '-2.000',
                    'stock_before' => '4.500',
                    'stock_after' => '2.500',
                    'reference_id' => 'TRX-260921-002',
                    'category' => 'out',
                    'note' => 'Penjualan espresso based',
                ],
                [
                    'occurred_at' => '20 Sep 2026 · 08:30',
                    'movement_type' => 'adjustment',
                    'movement_type_label' => 'Penyesuaian',
                    'quantity_change' => '+5.000',
                    'stock_before' => '0.000',
                    'stock_after' => '5.000',
                    'reference_id' => 'ADJ-260920-001',
                    'category' => 'in',
                    'note' => 'Restock roastery partner',
                ],
            ],
        ],
        [
            'id' => 2,
            'name' => 'Cup 16oz',
            'sku' => 'PKG-001',
            'category_name' => 'Kemasan',
            'current_stock' => '-12.000',
            'min_stock' => '20.000',
            'unit' => 'pcs',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '21 Sep 2026 · 11:30',
                    'movement_type' => 'sale',
                    'movement_type_label' => 'Penjualan',
                    'quantity_change' => '-12.000',
                    'stock_before' => '0.000',
                    'stock_after' => '-12.000',
                    'reference_id' => 'TRX-260921-004',
                    'category' => 'out',
                    'note' => 'Penjualan takeaway sebelum restock dicatat',
                ],
            ],
        ],
        [
            'id' => 3,
            'name' => 'Sirup Karamel',
            'sku' => 'RAW-002',
            'category_name' => 'Bahan Baku',
            'current_stock' => '0.000',
            'min_stock' => '2.000',
            'unit' => 'botol',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '21 Sep 2026 · 09:00',
                    'movement_type' => 'sale',
                    'movement_type_label' => 'Penjualan',
                    'quantity_change' => '-1.000',
                    'stock_before' => '1.000',
                    'stock_after' => '0.000',
                    'reference_id' => 'TRX-260921-001',
                    'category' => 'out',
                    'note' => 'Pemakaian sirup macchiato',
                ],
            ],
        ],
        [
            'id' => 4,
            'name' => 'Susu UHT 1L',
            'sku' => 'RAW-003',
            'category_name' => 'Bahan Baku',
            'current_stock' => '14.000',
            'min_stock' => '6.000',
            'unit' => 'liter',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '21 Sep 2026 · 07:00',
                    'movement_type' => 'adjustment',
                    'movement_type_label' => 'Penyesuaian',
                    'quantity_change' => '+10.000',
                    'stock_before' => '4.000',
                    'stock_after' => '14.000',
                    'reference_id' => 'ADJ-260921-001',
                    'category' => 'in',
                    'note' => 'Penerimaan supplier harian',
                ],
            ],
        ],
        [
            'id' => 5,
            'name' => 'Croissant Dough',
            'sku' => 'RAW-004',
            'category_name' => 'Bahan Baku',
            'current_stock' => '3.000',
            'min_stock' => '4.000',
            'unit' => 'box',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '21 Sep 2026 · 08:00',
                    'movement_type' => 'sale',
                    'movement_type_label' => 'Penjualan',
                    'quantity_change' => '-2.000',
                    'stock_before' => '5.000',
                    'stock_after' => '3.000',
                    'reference_id' => 'TRX-260921-001',
                    'category' => 'out',
                    'note' => 'Baking batch pagi',
                ],
            ],
        ],
        [
            'id' => 6,
            'name' => 'Sedotan Kertas',
            'sku' => 'PKG-002',
            'category_name' => 'Kemasan',
            'current_stock' => '150.000',
            'min_stock' => '50.000',
            'unit' => 'pcs',
            'status' => 'active',
            'movements' => [
                [
                    'occurred_at' => '19 Sep 2026 · 14:00',
                    'movement_type' => 'adjustment',
                    'movement_type_label' => 'Penyesuaian',
                    'quantity_change' => '+200.000',
                    'stock_before' => '0.000',
                    'stock_after' => '200.000',
                    'reference_id' => 'ADJ-260919-001',
                    'category' => 'in',
                    'note' => 'Pengadaan kemasan ramah lingkungan',
                ],
            ],
        ],
    ];

    $safeCount = 0;
    $lowCount = 0;
    $criticalCount = 0;

    foreach ($items as &$item) {
        $st = (float) $item['current_stock'];
        $min = (float) $item['min_stock'];

        if ($st < 0) {
            $stockStatus = 'negative';
            $criticalCount++;
        } elseif ($st == 0.0) {
            $stockStatus = 'empty';
            $criticalCount++;
        } elseif ($st <= $min) {
            $stockStatus = 'low';
            $lowCount++;
        } else {
            $stockStatus = 'safe';
            $safeCount++;
        }

        $item['stock_status'] = $stockStatus;
    }
    unset($item);

    return [
        'summary' => [
            'total_items' => count($items),
            'safe_stock' => $safeCount,
            'low_stock' => $lowCount,
            'critical_stock' => $criticalCount,
        ],
        'categories' => [
            'Bahan Baku',
            'Kemasan',
        ],
        'items' => $items,
    ];
};
