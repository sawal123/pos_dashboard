@props(['outlets'])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'inactive' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="hidden lg:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Alamat</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Perangkat</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Shift Open</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transaksi</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Terakhir</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Detail</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($outlets as $outlet)
                <tr class="outlet-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-900 dark:text-white">{{ $outlet['name'] }}</div>
                        <div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $outlet['code'] }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 max-w-[16rem]">
                        {{ $outlet['address'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($outlet['status_raw']) }}">
                            {{ $outlet['status'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                        {{ number_format((int) $outlet['device_count'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                        {{ number_format((int) $outlet['open_shift_count'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $formatRupiah($outlet['sales_total']) }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $outlet['sales_count'] }} transaksi</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        {{ $outlet['last_transaction_at'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button
                            type="button"
                            class="view-outlet-detail-btn inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            data-detail-url="{{ route('outlets.detail', $outlet['id']) }}"
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
