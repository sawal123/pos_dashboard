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

<div class="hidden lg:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Shift</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Waktu</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transaksi</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Estimasi Kas</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Detail</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($shifts as $shift)
                <tr class="shift-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-900 dark:text-white font-mono">{{ $shift['shift_number'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Dibuka {{ $shift['opened_at'] }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $shift['outlet_name'] }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($shift['status_raw']) }}">
                            {{ $shift['status'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        <div>Open: <span class="font-medium">{{ $shift['opened_at'] }}</span></div>
                        <div>Closed: <span class="font-medium">{{ $shift['closed_at'] }}</span></div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $formatRupiah($shift['sales_total']) }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $shift['sales_count'] }} transaksi</div>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                        {{ $formatRupiah($shift['estimated_cash']) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button
                            type="button"
                            class="view-shift-detail-btn inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            data-detail-url="{{ route('shifts.detail', $shift['id']) }}"
                        >
                            <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                            Detail
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
