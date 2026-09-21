@props([
    'metrics' => [
        'total_transactions' => 0,
        'total_sales' => 0,
        'average_transaction' => 0,
        'top_payment_method' => '-',
        'top_payment_percentage' => 0,
    ],
])

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
    {{-- Card 1: Total Transaksi --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Total Transaksi</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <i data-lucide="receipt" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-lg sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
            {{ number_format($metrics['total_transactions'], 0, ',', '.') }}
        </div>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Transaksi tercatat</p>
    </div>

    {{-- Card 2: Total Penjualan --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Total Penjualan</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <i data-lucide="banknote" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-lg sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
            Rp {{ number_format($metrics['total_sales'], 0, ',', '.') }}
        </div>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Volume penjualan kotor</p>
    </div>

    {{-- Card 3: Rata-rata Transaksi --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Rata-rata Transaksi</span>
            <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <i data-lucide="chart-pie" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-lg sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
            Rp {{ number_format($metrics['average_transaction'], 0, ',', '.') }}
        </div>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Rata-rata per tiket/struk</p>
    </div>

    {{-- Card 4: Pembayaran Terbanyak --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Pembayaran Terbanyak</span>
            <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                <i data-lucide="wallet-cards" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-lg sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
            {{ $metrics['top_payment_method'] }} <span class="text-sm font-semibold text-slate-500 dark:text-slate-400 font-normal">({{ $metrics['top_payment_percentage'] }}%)</span>
        </div>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Metode paling dominan</p>
    </div>
</div>
