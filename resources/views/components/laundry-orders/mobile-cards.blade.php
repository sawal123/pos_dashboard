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

<div class="lg:hidden space-y-3">
    @foreach($orders as $order)
        <article class="laundry-order-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-mono font-extrabold text-slate-900 dark:text-white break-all">{{ $order['transaction_number'] }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $order['sold_at'] ?? '-' }}</p>
                </div>
                @if($order['is_overdue'])
                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800">
                        <i data-lucide="alarm-clock" class="w-3 h-3"></i>
                        Terlambat
                    </span>
                @endif
            </div>

            <div class="space-y-1">
                <p class="font-semibold text-slate-900 dark:text-white">{{ $order['customer_name'] }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $order['customer_phone'] ?? '-' }} · {{ $order['outlet_name'] }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $orderBadgeClass($order['order_status_category']) }}">
                    {{ $order['order_status'] }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $paymentBadgeClass($order['payment_status_raw']) }}">
                    {{ $order['payment_status'] }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Estimasi Selesai</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $order['estimated_completed_at'] ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Total</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $formatRupiah($order['total_amount']) }}</span>
                </div>
            </div>

            <button
                type="button"
                class="view-laundry-order-detail-btn w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-detail-url="{{ route('laundry-orders.detail', $order['id']) }}"
            >
                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                Lihat Detail
            </button>
        </article>
    @endforeach
</div>
