<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $sale->transaction_number }} - {{ $business->name }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 10px;
            max-width: 320px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .receipt-logo {
            max-height: 48px;
            max-width: 90px;
            margin: 0 auto 8px auto;
            display: block;
            object-fit: contain;
        }
        .store-name {
            font-size: 15px;
            font-weight: bold;
        }
        .meta {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }
        .items {
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .item-sub {
            font-size: 10px;
            color: #555;
            padding-left: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .total-row {
            font-weight: bold;
            font-size: 13px;
            border-top: 1px dashed #000;
            padding-top: 4px;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
            margin-top: 8px;
        }
        @media print {
            body {
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
            .receipt-logo {
                display: block !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 12px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 12px; font-weight: bold; cursor: pointer;">
            🖨️ Cetak Struk
        </button>
        <button onclick="window.close()" style="padding: 6px 12px; margin-left: 6px; cursor: pointer;">
            Tutup
        </button>
    </div>

    <div class="header">
        @if($business->receiptLogoUrl())
            <div class="logo-wrapper" style="text-align: center; margin-bottom: 8px;">
                <img src="{{ $business->receiptLogoUrl() }}" alt="Logo" class="receipt-logo">
            </div>
        @endif
        <div class="store-name">{{ $business->name }}</div>
        <div class="meta">{{ $sale->outlet?->name ?? 'Outlet' }}</div>
        @if(!empty($business->receipt_address))
            <div class="meta">{!! nl2br(e($business->receipt_address)) !!}</div>
        @elseif($sale->outlet?->address)
            <div class="meta">{{ $sale->outlet->address }}</div>
        @endif
        <div class="meta" style="margin-top: 4px;">
            No: {{ $sale->transaction_number }}<br>
            Waktu: {{ $sale->sold_at?->copy()->setTimezone(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i:s') }}<br>
            Kasir: {{ $sale->shift ? 'Shift '.$sale->shift->shift_number : 'Web Kasir' }}
            @if($sale->customer)
                <br>Pelanggan: {{ $sale->customer->name }}
            @endif
        </div>
    </div>

    <div class="items">
        @foreach($sale->items as $item)
            <div class="item-row">
                <span>{{ $item->product_name }}</span>
                <span>Rp {{ number_format($item->line_total, 0, ',', '.') }}</span>
            </div>
            @if(!empty($item->variant_summary))
                <div class="item-sub" style="font-size: 10px; color: #64748b; font-style: italic; margin-top: -2px; margin-bottom: 2px;">
                    {{ $item->variant_summary }}
                </div>
            @endif
            <div class="item-sub">
                {{ $item->quantity }} {{ $item->unit }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}
            </div>
        @endforeach
    </div>

    <div>
        <div class="summary-row">
            <span>Subtotal:</span>
            <span>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</span>
        </div>
        @if($sale->discount_amount > 0)
            <div class="summary-row" style="color: #059669;">
                <span>Diskon:</span>
                <span>- Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($sale->tax_amount > 0)
            <div class="summary-row">
                <span>Pajak:</span>
                <span>+ Rp {{ number_format($sale->tax_amount, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="summary-row total-row">
            <span>TOTAL:</span>
            <span>Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row" style="margin-top: 4px;">
            <span>Bayar ({{ strtoupper($sale->payment_method) }}):</span>
            <span>Rp {{ number_format($sale->cash_received, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>Kembalian:</span>
            <span>Rp {{ number_format($sale->change_amount, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="footer">
        @if(!empty($business->receipt_footer))
            <p>{!! nl2br(e($business->receipt_footer)) !!}</p>
        @else
            <p>Terima kasih atas kunjungan Anda!</p>
            <p style="margin-top: 2px;">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        @endif
        <p style="margin-top: 4px; font-size: 8px; color: #888;">Net Kasir System</p>
    </div>
</body>
</html>
