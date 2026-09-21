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

    return [
        'summary' => [
            'total_items' => 6,
            'safe_stock' => 2,
            'low_stock' => 2,
            'critical_stock' => 2,
        ],
        'categories' => [
            'Bahan Baku',
            'Kemasan',
        ],
        'items' => [
            [
                'id' => 1,
                'name' => 'Biji Kopi Arabika',
                'sku' => 'RAW-001',
                'category_name' => 'Bahan Baku',
                'current_stock' => '2.500',
                'min_stock' => '5.000',
                'unit' => 'kg',
                'status' => 'low',
                'movements' => [
                    [
                        'occurred_at' => '21 Sep 2026 · 10:15',
                        'movement_type' => 'sale',
                        'movement_type_label' => 'Penjualan',
                        'quantity_change' => '-2.000',
                        'stock_before' => '4.500',
                        'stock_after' => '2.500',
                        'reference_id' => 'TRX-260921-002',
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
                'status' => 'negative',
                'movements' => [
                    [
                        'occurred_at' => '21 Sep 2026 · 11:30',
                        'movement_type' => 'sale',
                        'movement_type_label' => 'Penjualan',
                        'quantity_change' => '-12.000',
                        'stock_before' => '0.000',
                        'stock_after' => '-12.000',
                        'reference_id' => 'TRX-260921-004',
                        'note' => 'Penjualan takeaway sebelum restock tercatat',
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
                'status' => 'empty',
                'movements' => [
                    [
                        'occurred_at' => '21 Sep 2026 · 09:00',
                        'movement_type' => 'sale',
                        'movement_type_label' => 'Penjualan',
                        'quantity_change' => '-1.000',
                        'stock_before' => '1.000',
                        'stock_after' => '0.000',
                        'reference_id' => 'TRX-260921-001',
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
                'status' => 'safe',
                'movements' => [
                    [
                        'occurred_at' => '21 Sep 2026 · 07:00',
                        'movement_type' => 'adjustment',
                        'movement_type_label' => 'Penyesuaian',
                        'quantity_change' => '+10.000',
                        'stock_before' => '4.000',
                        'stock_after' => '14.000',
                        'reference_id' => 'ADJ-260921-001',
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
                'status' => 'low',
                'movements' => [
                    [
                        'occurred_at' => '21 Sep 2026 · 08:00',
                        'movement_type' => 'sale',
                        'movement_type_label' => 'Penjualan',
                        'quantity_change' => '-2.000',
                        'stock_before' => '5.000',
                        'stock_after' => '3.000',
                        'reference_id' => 'TRX-260921-001',
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
                'status' => 'safe',
                'movements' => [
                    [
                        'occurred_at' => '19 Sep 2026 · 14:00',
                        'movement_type' => 'adjustment',
                        'movement_type_label' => 'Penyesuaian',
                        'quantity_change' => '+200.000',
                        'stock_before' => '0.000',
                        'stock_after' => '200.000',
                        'reference_id' => 'ADJ-260919-001',
                        'note' => 'Pengadaan kemasan ramah lingkungan',
                    ],
                ],
            ],
        ],
    ];
};
