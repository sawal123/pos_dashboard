@props([
    'metrics' => [
        'total_transactions' => 0,
        'total_sales' => 0,
        'average_transaction' => 0,
        'top_payment_method' => '-',
        'top_payment_percentage' => 0,
        'success_count' => 0,
        'success_sales' => 0,
        'processing_count' => 0,
        'processing_amount' => 0,
        'cancelled_refund_count' => 0,
        'cancelled_refund_amount' => 0,
    ],
    'currentFilters' => [],
])

@php
    $baseQuery = array_filter([
        'q' => $currentFilters['q'] ?? null,
        'date' => $currentFilters['date'] ?? null,
        'start_date' => $currentFilters['start_date'] ?? null,
        'end_date' => $currentFilters['end_date'] ?? null,
        'outlet_id' => $currentFilters['outlet_id'] ?? null,
        'payment_method' => $currentFilters['payment_method'] ?? null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== 'all');

    $activeStatus = strtolower((string) ($currentFilters['status'] ?? 'all'));
    $activePayStatus = strtolower((string) ($currentFilters['payment_status'] ?? 'all'));

    $isSuccessFilter = in_array($activeStatus, ['siap', 'completed', 'selesai'], true) && $activePayStatus === 'paid';
    $isProcessingFilter = in_array($activeStatus, ['proses', 'processing', 'diproses', 'masuk'], true);
    $isCancelledRefundFilter = in_array($activePayStatus, ['refund', 'refunded'], true)
        || in_array($activeStatus, ['batal', 'cancelled', 'canceled', 'dibatalkan'], true);

    $successUrl = route('transactions.index', array_merge($baseQuery, ['status' => 'siap', 'payment_status' => 'paid']));
    $processingUrl = route('transactions.index', array_merge($baseQuery, ['status' => 'proses', 'payment_status' => 'all']));
    $cancelledRefundUrl = route('transactions.index', array_merge($baseQuery, ['status' => 'all', 'payment_status' => 'refund']));
@endphp

<div class="space-y-3 md:space-y-4">
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
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Hanya transaksi sukses</p>
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

{{-- Detail Breakdown Status Transaksi: Sukses vs Proses vs Batal / Refund --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    {{-- Transaksi Sukses --}}
    <a
        href="{{ $successUrl }}"
        class="group flex items-center justify-between gap-3 p-3.5 sm:px-4 rounded-2xl bg-white dark:bg-slate-900 border {{ $isSuccessFilter ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/90 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-800' }} shadow-xs transition-all"
    >
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 block">Transaksi Sukses</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                    Rp {{ number_format($metrics['success_sales'] ?? 0, 0, ',', '.') }}
                </span>
            </div>
        </div>
        <div class="text-right shrink-0">
            <span class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400 tabular-nums">
                {{ number_format($metrics['success_count'] ?? 0, 0, ',', '.') }}
            </span>
            <span class="block text-[10px] text-slate-400">transaksi</span>
        </div>
    </a>

    {{-- Transaksi Proses --}}
    <a
        href="{{ $processingUrl }}"
        class="group flex items-center justify-between gap-3 p-3.5 sm:px-4 rounded-2xl bg-white dark:bg-slate-900 border {{ $isProcessingFilter ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200/90 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-800' }} shadow-xs transition-all"
    >
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200/70 dark:border-blue-800/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                <i data-lucide="clock" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 block">Transaksi Proses</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                    Rp {{ number_format($metrics['processing_amount'] ?? 0, 0, ',', '.') }}
                </span>
            </div>
        </div>
        <div class="text-right shrink-0">
            <span class="text-lg font-extrabold text-blue-700 dark:text-blue-400 tabular-nums">
                {{ number_format($metrics['processing_count'] ?? 0, 0, ',', '.') }}
            </span>
            <span class="block text-[10px] text-slate-400">transaksi</span>
        </div>
    </a>

    {{-- Transaksi Batal / Refund --}}
    <a
        href="{{ $cancelledRefundUrl }}"
        class="group flex items-center justify-between gap-3 p-3.5 sm:px-4 rounded-2xl bg-white dark:bg-slate-900 border {{ $isCancelledRefundFilter ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200/90 dark:border-slate-800 hover:border-rose-300 dark:hover:border-rose-800' }} shadow-xs transition-all"
    >
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400 block">Batal / Refund</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                    Rp {{ number_format($metrics['cancelled_refund_amount'] ?? 0, 0, ',', '.') }}
                </span>
            </div>
        </div>
        <div class="text-right shrink-0">
            <span class="text-lg font-extrabold text-rose-700 dark:text-rose-400 tabular-nums">
                {{ number_format($metrics['cancelled_refund_count'] ?? 0, 0, ',', '.') }}
            </span>
            <span class="block text-[10px] text-slate-400">transaksi</span>
        </div>
    </a>
</div>
</div>
