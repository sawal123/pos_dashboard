@props(['orders'])

@php
    $formatRupiah = fn ($amount): string => 'Rp '.number_format((int) $amount, 0, ',', '.');

    $orderBadgeClass = fn (string $category): string => match ($category) {
        'incoming' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        'processing' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800',
        'ready' => 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
        'done' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    };

    $paymentBadgeClass = fn (string $raw): string => match ($raw) {
        'paid' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'unpaid' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    };
@endphp

<div class="hidden lg:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nomor</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pelanggan</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Estimasi</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pengerjaan</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pembayaran</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Detail</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($orders as $order)
                <tr class="laundry-order-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-900 dark:text-white font-mono break-all">{{ $order['transaction_number'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $order['sold_at'] ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $order['customer_name'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $order['customer_phone'] ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ $order['outlet_name'] }}</td>
                    <td class="px-4 py-3">
                        <div class="text-xs text-slate-600 dark:text-slate-300">{{ $order['estimated_completed_at'] ?? '-' }}</div>
                        @if($order['is_overdue'])
                            <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800">
                                <i data-lucide="alarm-clock" class="w-3 h-3"></i>
                                Terlambat
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $orderBadgeClass($order['order_status_category']) }}">
                            {{ $order['order_status'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $paymentBadgeClass($order['payment_status_raw']) }}">
                            {{ $order['payment_status'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                        {{ $formatRupiah($order['total_amount']) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button
                            type="button"
                            class="view-laundry-order-detail-btn inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            data-detail-url="{{ route('laundry-orders.detail', $order['id']) }}"
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
