@props([
    'transactions' => [],
])

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse" id="desktopTransactionsTable">
            <thead>
                <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider select-none">
                    <th class="py-3.5 px-4 font-bold">Nomor Transaksi</th>
                    <th class="py-3.5 px-4 font-bold">Tanggal & Waktu</th>
                    <th class="py-3.5 px-4 font-bold">Outlet</th>
                    <th class="py-3.5 px-4 font-bold">Pelanggan</th>
                    <th class="py-3.5 px-4 font-bold">Pembayaran</th>
                    <th class="py-3.5 px-4 font-bold">Status Pembayaran</th>
                    <th class="py-3.5 px-4 font-bold text-right">Total</th>
                    <th class="py-3.5 px-4 font-bold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200" id="desktopTransactionsBody">
                @foreach($transactions as $trx)
                    @php
                        $isPaid = $trx['payment_status'] === 'Lunas';
                        $isCancelled = $trx['status'] === 'Dibatalkan';
                        $customer = $trx['customer_name'] ?: 'Pelanggan Umum';
                    @endphp
                    <tr
                        class="table-row hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors transaction-row"
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
                        {{-- Nomor Transaksi --}}
                        <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span>{{ $trx['transaction_number'] }}</span>
                                @if($isCancelled)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400">
                                        Batal
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- Tanggal & Waktu --}}
                        <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ $trx['sold_at'] }}
                        </td>

                        {{-- Outlet --}}
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-300">
                                <i data-lucide="store" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                <span>{{ $trx['outlet_name'] }}</span>
                            </span>
                        </td>

                        {{-- Pelanggan --}}
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="{{ empty($trx['customer_name']) ? 'text-slate-400 dark:text-slate-500 italic' : 'font-medium text-slate-800 dark:text-slate-200' }}">
                                {{ $customer }}
                            </span>
                        </td>

                        {{-- Pembayaran --}}
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                @if($trx['payment_method'] === 'QRIS')
                                    <i data-lucide="qr-code" class="w-3 h-3 text-indigo-500"></i>
                                @elseif($trx['payment_method'] === 'Tunai')
                                    <i data-lucide="banknote" class="w-3 h-3 text-emerald-500"></i>
                                @elseif($trx['payment_method'] === 'Kartu')
                                    <i data-lucide="credit-card" class="w-3 h-3 text-amber-500"></i>
                                @else
                                    <i data-lucide="wallet" class="w-3 h-3 text-slate-400"></i>
                                @endif
                                <span>{{ $trx['payment_method'] }}</span>
                            </span>
                        </td>

                        {{-- Status Pembayaran --}}
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @if($isPaid)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Lunas</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>Belum Lunas</span>
                                </span>
                            @endif
                        </td>

                        {{-- Total --}}
                        <td class="py-3.5 px-4 whitespace-nowrap text-right font-extrabold text-slate-900 dark:text-white tabular-nums text-sm">
                            Rp {{ number_format($trx['total_amount'], 0, ',', '.') }}
                        </td>

                        {{-- Aksi --}}
                        <td class="py-3.5 px-4 whitespace-nowrap text-center">
                            <button
                                type="button"
                                class="view-detail-btn inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                data-id="{{ $trx['id'] }}"
                                aria-label="Lihat detail transaksi {{ $trx['transaction_number'] }}"
                            >
                                <span>Lihat Detail</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
