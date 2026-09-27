@php
    /** @var \App\Services\Dashboard\Reporting\ReportDocument $document */
    $rupiah = function (int|float|string $value): string {
        $number = (float) $value;

        return 'Rp '.number_format($number, floor($number) === $number ? 0 : 2, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $document->businessName }}</title>
    <style>
        @page { margin: 28px 32px 46px 32px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #0f172a; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 12px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-table td.label { width: 130px; color: #64748b; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th,
        table.data td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        table.data th { background: #eef2ff; font-weight: bold; }
        table.data td.num,
        table.data th.num { text-align: right; }
        table.data tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .summary-grid { width: 100%; border-collapse: collapse; }
        .summary-grid td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; width: 25%; }
        .summary-label { color: #64748b; font-size: 9px; }
        .summary-value { font-size: 12px; font-weight: bold; }
        .empty { border: 1px dashed #cbd5e1; padding: 10px; color: #64748b; text-align: center; }
        .note { margin-top: 12px; border: 1px solid #fde68a; background: #fffbeb; padding: 6px 8px; page-break-inside: avoid; }
        .footer { position: fixed; bottom: -32px; left: 0; right: 0; color: #94a3b8; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Penjualan &amp; Pengeluaran</h1>
        <div class="muted">{{ $document->businessName }}</div>
    </div>

    <table class="meta-table">
        @foreach($document->meta() as $meta)
            <tr>
                <td class="label">{{ $meta['label'] }}</td>
                <td>: {{ $meta['value'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="section-title">Ringkasan</div>
    <table class="summary-grid">
        <tr>
            @foreach($document->summaryRows() as $summary)
                <td>
                    <div class="summary-label">{{ $summary['label'] }}</div>
                    <div class="summary-value">
                        {{ $summary['type'] === 'integer'
                            ? number_format((int) $summary['value'], 0, ',', '.')
                            : $rupiah($summary['value']) }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    @if($document->isEmpty())
        <div class="section-title">Data</div>
        <div class="empty">Tidak ada data penjualan atau pengeluaran pada periode/filter ini.</div>
    @else
        <div class="section-title">Tren Penjualan</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="num">Total Penjualan</th>
                    <th class="num">Jumlah Transaksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($document->dataset->salesTrend as $trend)
                    <tr>
                        <td>{{ $trend['date'] }}</td>
                        <td class="num">{{ $rupiah($trend['total_sales']) }}</td>
                        <td class="num">{{ number_format((int) $trend['transaction_count'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Tidak ada transaksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">Breakdown Metode Pembayaran</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Metode Pembayaran</th>
                    <th class="num">Jumlah Transaksi</th>
                    <th class="num">Total Nilai</th>
                    <th class="num">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @forelse($document->dataset->paymentBreakdown as $payment)
                    <tr>
                        <td>{{ $payment['payment_method'] }}</td>
                        <td class="num">{{ number_format((int) $payment['transaction_count'], 0, ',', '.') }}</td>
                        <td class="num">{{ $rupiah($payment['total_amount']) }}</td>
                        <td class="num">{{ (int) $payment['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Tidak ada data pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">Breakdown Kategori Pengeluaran</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="num">Jumlah Pengeluaran</th>
                    <th class="num">Total Nilai</th>
                    <th class="num">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @forelse($document->dataset->expenseBreakdown as $expense)
                    <tr>
                        <td>{{ $expense['category'] }}</td>
                        <td class="num">{{ number_format((int) $expense['expense_count'], 0, ',', '.') }}</td>
                        <td class="num">{{ $rupiah($expense['total_amount']) }}</td>
                        <td class="num">{{ (int) $expense['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Tidak ada data pengeluaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($document->note() !== null)
        <div class="note">{{ $document->note() }}</div>
    @endif

    <div class="footer">Dibuat otomatis oleh NexaPOS · {{ $document->generatedAt }} ({{ $document->timezone }})</div>
</body>
</html>
