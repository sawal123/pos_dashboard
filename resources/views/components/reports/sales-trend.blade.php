@props([
    'trend' => [],
    'periodLabel' => 'Periode Terpilih',
])

@php
    $maxAmount = 0;
    foreach ($trend as $d) {
        if (($d['total_sales'] ?? 0) > $maxAmount) {
            $maxAmount = $d['total_sales'];
        }
    }
@endphp

<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Tren Penjualan
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Aktivitas omzet dan volume transaksi harian.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold border border-indigo-100 dark:border-indigo-800/60">
                <i data-lucide="chart-bar" class="w-3.5 h-3.5"></i>
                <span id="salesTrendPeriodLabel">{{ $periodLabel }}</span>
            </span>
        </div>
    </div>

    {{-- Horizontal Bars Container --}}
    @if(count($trend) > 0)
        <div id="salesTrendContainer" class="space-y-3.5 pt-1">
            @foreach($trend as $d)
                @php
                    $salesAmount = (int) ($d['total_sales'] ?? 0);
                    $pct = $maxAmount > 0 ? max(8, (int) round(($salesAmount / $maxAmount) * 100)) : 0;
                @endphp
                <div class="space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                        <span class="font-bold text-slate-900 dark:text-white">{{ $d['date'] }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $d['transaction_count'] }} transaksi</span>
                            <span class="font-extrabold text-slate-900 dark:text-white tabular-nums">
                                Rp {{ number_format($salesAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    <div class="h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all duration-300"
                            style="width: {{ $pct }}%;"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div id="salesTrendEmpty" class="py-8 text-center text-xs text-slate-400 italic">
            Tidak ada data penjualan pada periode ini.
        </div>
    @endif
</div>
