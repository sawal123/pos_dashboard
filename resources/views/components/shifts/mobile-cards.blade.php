@props(['shifts'])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'open' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'closed' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="lg:hidden space-y-3">
    @foreach($shifts as $shift)
        <article class="shift-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-mono font-extrabold text-slate-900 dark:text-white break-all">{{ $shift['shift_number'] }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $shift['outlet_name'] }}</p>
                </div>
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($shift['status_raw']) }}">
                    {{ $shift['status'] }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Dibuka</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $shift['opened_at'] }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Ditutup</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $shift['closed_at'] }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Transaksi</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $shift['sales_count'] }} / {{ $formatRupiah($shift['sales_total']) }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Estimasi Kas</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $formatRupiah($shift['estimated_cash']) }}</span>
                </div>
            </div>

            <button
                type="button"
                class="view-shift-detail-btn w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-detail-url="{{ route('shifts.detail', $shift['id']) }}"
            >
                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                Lihat Detail
            </button>
        </article>
    @endforeach
</div>
