@props(['customers'])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'inactive' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            'deleted' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="lg:hidden space-y-3" id="mobileCustomersContainer">
    @foreach($customers as $customer)
        <article class="customer-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-900 dark:text-white break-words">{{ $customer['name'] }}</h2>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 space-y-0.5">
                        @if($customer['phone'])
                            <p class="inline-flex items-center gap-1">
                                <i data-lucide="phone" class="w-3 h-3 text-slate-400"></i>{{ $customer['phone'] }}
                            </p>
                        @endif
                        @if($customer['email'])
                            <p class="inline-flex items-center gap-1">
                                <i data-lucide="mail" class="w-3 h-3 text-slate-400"></i>{{ $customer['email'] }}
                            </p>
                        @endif
                        @if(! $customer['phone'] && ! $customer['email'])
                            <p class="text-slate-400 dark:text-slate-500 italic">Tanpa kontak</p>
                        @endif
                    </div>
                </div>
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($customer['status_raw']) }}">
                    {{ $customer['status'] }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs pt-2 border-t border-slate-100 dark:border-slate-800/70">
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Transaksi Selesai</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ number_format((int) $customer['transactions_count'], 0, ',', '.') }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Total Pembelian</span>
                    <span class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $formatRupiah($customer['purchase_total']) }}</span>
                </div>
                <div class="col-span-2">
                    <span class="block text-slate-500 dark:text-slate-400">Pembelian Terakhir</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $customer['last_purchase_at'] ?? '—' }}</span>
                </div>
            </div>

            <button
                type="button"
                class="view-customer-detail-btn w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                data-detail-url="{{ route('customers.detail', $customer['id']) }}"
                aria-label="Lihat detail pelanggan {{ $customer['name'] }}"
            >
                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                Lihat Detail
            </button>
        </article>
    @endforeach
</div>
