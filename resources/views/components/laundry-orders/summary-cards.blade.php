@props(['summary'])

@php
    $formatRupiah = fn ($amount): string => 'Rp '.number_format((int) $amount, 0, ',', '.');

    $cards = [
        ['id' => 'laundrySummaryTotal', 'label' => 'Total Pesanan', 'value' => (int) ($summary['total_orders'] ?? 0), 'icon' => 'washing-machine', 'class' => 'text-slate-700 dark:text-slate-200'],
        ['id' => 'laundrySummaryIncoming', 'label' => 'Masuk', 'value' => (int) ($summary['masuk'] ?? 0), 'icon' => 'inbox', 'class' => 'text-amber-700 dark:text-amber-300'],
        ['id' => 'laundrySummaryProcessing', 'label' => 'Diproses', 'value' => (int) ($summary['diproses'] ?? 0), 'icon' => 'loader', 'class' => 'text-sky-700 dark:text-sky-300'],
        ['id' => 'laundrySummaryReady', 'label' => 'Siap Diambil', 'value' => (int) ($summary['siap_diambil'] ?? 0), 'icon' => 'package-check', 'class' => 'text-indigo-700 dark:text-indigo-300'],
        ['id' => 'laundrySummaryDone', 'label' => 'Selesai', 'value' => (int) ($summary['selesai'] ?? 0), 'icon' => 'check-circle-2', 'class' => 'text-emerald-700 dark:text-emerald-300'],
        ['id' => 'laundrySummaryOverdue', 'label' => 'Terlambat', 'value' => (int) ($summary['overdue'] ?? 0), 'icon' => 'alarm-clock', 'class' => 'text-rose-700 dark:text-rose-300'],
    ];
@endphp

<section class="space-y-3">
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($cards as $card)
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 truncate">{{ $card['label'] }}</p>
                        <p id="{{ $card['id'] }}" class="mt-1 text-2xl font-extrabold tabular-nums {{ $card['class'] }}">
                            {{ number_format($card['value'], 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="{{ $card['icon'] }}" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Revenue is intentionally separated from the order counters. Its formula is
         transaction status = completed AND payment_status = paid; it does NOT
         require order_status = Selesai, so a paid-and-completed order still being
         processed is included, while canceled/void/unpaid orders are excluded. --}}
    <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-900/60 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 shrink-0 rounded-xl bg-white/70 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-300">
                <i data-lucide="banknote" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Total Transaksi Lunas</p>
                <p class="text-[11px] text-indigo-600/80 dark:text-indigo-300/80">
                    Total nilai transaksi laundry yang berstatus transaksi
                    <span class="font-semibold">Selesai (completed)</span> dan
                    <span class="font-semibold">Lunas (paid)</span>, terlepas dari status pengerjaan laundry.
                    Transaksi batal, void, dan belum lunas tidak dihitung.
                </p>
            </div>
        </div>
        <p id="laundrySummaryRevenue" class="text-xl font-extrabold text-indigo-900 dark:text-indigo-100 tabular-nums">
            {{ $formatRupiah($summary['paid_revenue'] ?? 0) }}
        </p>
    </div>
</section>
