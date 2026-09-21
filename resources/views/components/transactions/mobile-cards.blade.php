@props([
    'transactions' => [],
])
{{-- $transactions: LengthAwarePaginator with ->through() mapped arrays --}}

<div class="block md:hidden space-y-3" id="mobileTransactionsContainer">
    @foreach($transactions as $trx)
        @php
            $payRaw = $trx['payment_status_raw'] ?? $trx['payment_status'];
            $isCancelled = in_array($trx['status_raw'] ?? $trx['status'], ['cancelled', 'canceled']);
            $customer = $trx['customer_name'] ?: 'Pelanggan Umum';
        @endphp
        <div
            class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors transaction-card space-y-3"
            data-id="{{ $trx['id'] }}"
            data-trx="{{ $trx['transaction_number'] }}"
            data-customer="{{ strtolower($customer) }}"
            data-sold-at="{{ $trx['sold_at_raw'] }}"
            data-outlet="{{ $trx['outlet_name'] }}"
            data-payment="{{ $trx['payment_method'] }}"
            data-payment-status="{{ $trx['payment_status'] }}"
            data-status="{{ $trx['status'] }}"
            data-raw="{{ json_encode($trx) }}"
        >
            {{-- Header: TRX number + Total --}}
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-sm text-slate-900 dark:text-white truncate">
                            {{ $trx['transaction_number'] }}
                        </span>
                        @if($isCancelled)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                Batal
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                        {{ $trx['sold_at'] }}
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-base font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                        Rp {{ number_format($trx['total_amount'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Customer & Outlet Info --}}
            <div class="flex items-center justify-between gap-2 text-xs pt-2 border-t border-slate-100 dark:border-slate-800/70">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Pelanggan</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-200 truncate mt-0.5 {{ empty($trx['customer_name']) ? 'italic text-slate-400 dark:text-slate-500' : '' }}">
                        {{ $customer }}
                    </p>
                </div>
                <div class="text-right min-w-0 flex-1">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Outlet</p>
                    <p class="font-semibold text-slate-800 dark:text-slate-200 truncate mt-0.5">
                        {{ $trx['outlet_name'] }}
                    </p>
                </div>
            </div>

            {{-- Badges: Payment Method & Payment Status --}}
            <div class="flex items-center justify-between gap-2 pt-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                    @if($trx['payment_method'] === 'QRIS')
                        <i data-lucide="qr-code" class="w-3.5 h-3.5 text-indigo-500"></i>
                    @elseif($trx['payment_method'] === 'Tunai')
                        <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-500"></i>
                    @elseif($trx['payment_method'] === 'Kartu')
                        <i data-lucide="credit-card" class="w-3.5 h-3.5 text-amber-500"></i>
                    @else
                        <i data-lucide="wallet" class="w-3.5 h-3.5 text-slate-400"></i>
                    @endif
                    <span>{{ $trx['payment_method'] }}</span>
                </span>

                @if($payRaw === 'paid')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>Lunas</span>
                    </span>
                @elseif($payRaw === 'unpaid')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Belum Lunas</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span>{{ $trx['payment_status'] }}</span>
                    </span>
                @endif
            </div>

            {{-- Action: Lihat Detail button --}}
            <button
                type="button"
                class="view-detail-btn w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700 bg-slate-50/50 hover:bg-indigo-50/50 dark:bg-slate-800/40 dark:hover:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
                data-id="{{ $trx['id'] }}"
                aria-label="Lihat detail transaksi {{ $trx['transaction_number'] }}"
            >
                <span>Lihat Detail</span>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>
    @endforeach
</div>
