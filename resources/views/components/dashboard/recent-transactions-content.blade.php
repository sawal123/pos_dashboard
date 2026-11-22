@props([
    'transactions' => [],
    'canEdit' => false,
    'canAddItems' => false,
])

@if(empty($transactions))
    <div class="px-5 py-12 text-center flex flex-col items-center justify-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3 text-slate-400 dark:text-slate-500 border border-slate-200/60 dark:border-slate-700/60">
            <i data-lucide="receipt" class="w-7 h-7"></i>
        </div>
        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 tracking-tight">Belum Ada Transaksi</h3>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mt-1 mb-4 leading-relaxed">
            Data transaksi penjualan akan muncul di sini setelah register kasir mencatat penjualan.
        </p>
        <a href="{{ route('transactions.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline text-xs flex items-center gap-1">
            Buka Halaman Transaksi <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>
@else
    {{-- 1. Desktop & Tablet Table View --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs md:text-sm border-collapse">
            <thead>
                <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/30 text-slate-500 dark:text-slate-400 font-semibold uppercase text-[11px] tracking-wider">
                    <th class="px-4 py-3.5">Invoice</th>
                    <th class="px-4 py-3.5">Waktu</th>
                    <th class="px-4 py-3.5">Pelanggan</th>
                    <th class="px-4 py-3.5">Outlet</th>
                    <th class="px-4 py-3.5">Pembayaran</th>
                    <th class="px-4 py-3.5 text-right">Total</th>
                    <th class="px-4 py-3.5">Status Bayar</th>
                    <th class="px-4 py-3.5 text-right">Status</th>
                    <th class="px-4 py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @foreach($transactions as $tx)
                    <tr
                        class="table-row dash-tx-row hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors"
                        data-detail="{{ json_encode($tx['detail'] ?? []) }}"
                    >
                        <td class="px-4 py-3.5 font-semibold text-slate-900 dark:text-white">
                            {{ $tx['transaction_number'] }}
                        </td>
                        <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            {{ $tx['sold_at'] }}
                        </td>
                        <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300 font-medium">
                            {{ $tx['customer_name'] }}
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                            {{ $tx['outlet_name'] }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-medium">
                                {{ $tx['payment_method'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                            {{ $tx['total_amount'] }}
                        </td>
                        <td class="px-4 py-3.5">
                            @if($tx['payment_status_raw'] === 'paid')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                    {{ $tx['payment_status'] }}
                                </span>
                            @elseif($tx['payment_status_raw'] === 'unpaid')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                                    {{ $tx['payment_status'] }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ $tx['payment_status'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            @if($tx['status_raw'] === 'completed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                    {{ $tx['status'] }}
                                </span>
                            @elseif(in_array($tx['status_raw'], ['cancelled', 'canceled'], true))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/80 dark:border-rose-800/60">
                                    {{ $tx['status'] }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ $tx['status'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="dash-tx-detail-btn inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 dark:hover:bg-indigo-950/50 dark:hover:border-indigo-800 dark:hover:text-indigo-400 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                                    title="Detail Transaksi"
                                    aria-label="Detail Transaksi {{ $tx['transaction_number'] }}"
                                >
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                                <a
                                    href="{{ route('pos.receipt', ['saleId' => $tx['id']]) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                                    title="Cetak Struk"
                                    aria-label="Cetak Struk {{ $tx['transaction_number'] }}"
                                >
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                                @if($canEdit)
                                    <button
                                        type="button"
                                        class="dash-tx-edit-btn inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-600 dark:hover:bg-amber-950/50 dark:hover:border-amber-800 dark:hover:text-amber-400 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                                        title="Edit Transaksi"
                                        aria-label="Edit Transaksi {{ $tx['transaction_number'] }}"
                                    >
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </button>
                                @endif
                                @if($canAddItems && ($tx['is_proses'] ?? false))
                                    <a
                                        href="{{ route('transactions.index', ['q' => $tx['transaction_number']]) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-emerald-200 dark:border-emerald-800/70 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 transition-colors"
                                        title="Tambah Pesanan (Status Proses)"
                                        aria-label="Tambah Pesanan {{ $tx['transaction_number'] }}"
                                    >
                                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- 2. Mobile Responsive Card View --}}
    <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-800/80 p-2">
        @foreach($transactions as $tx)
            <div
                class="dash-tx-row p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                data-detail="{{ json_encode($tx['detail'] ?? []) }}"
            >
                <div class="flex items-start justify-between gap-2 mb-1.5">
                    <div>
                        <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $tx['transaction_number'] }}</span>
                        <span class="text-[11px] text-slate-400 ml-1.5">{{ $tx['sold_at'] }}</span>
                    </div>
                    <span class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ $tx['total_amount'] }}</span>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-2">
                    <span class="font-medium">{{ $tx['customer_name'] }}</span>
                    <span class="text-[11px] text-slate-400">Outlet: {{ $tx['outlet_name'] }}</span>
                </div>

                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800/60 text-xs">
                    <span class="text-slate-500">{{ $tx['payment_method'] }}</span>
                    <div class="flex items-center gap-1.5">
                        @if($tx['payment_status_raw'] === 'paid')
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                {{ $tx['payment_status'] }}
                            </span>
                        @elseif($tx['payment_status_raw'] === 'unpaid')
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                                {{ $tx['payment_status'] }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                {{ $tx['payment_status'] }}
                            </span>
                        @endif
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tx['status_raw'] === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : (in_array($tx['status_raw'], ['cancelled', 'canceled'], true) ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                            {{ $tx['status'] }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-1.5 pt-2.5 mt-2 border-t border-slate-100 dark:border-slate-800/60">
                    <button
                        type="button"
                        class="dash-tx-detail-btn inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 dark:hover:bg-indigo-950/50 dark:hover:border-indigo-800 dark:hover:text-indigo-400 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                        title="Detail Transaksi"
                        aria-label="Detail Transaksi {{ $tx['transaction_number'] }}"
                    >
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <a
                        href="{{ route('pos.receipt', ['saleId' => $tx['id']]) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                        title="Cetak Struk"
                        aria-label="Cetak Struk {{ $tx['transaction_number'] }}"
                    >
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </a>
                    @if($canEdit)
                        <button
                            type="button"
                            class="dash-tx-edit-btn inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-600 dark:hover:bg-amber-950/50 dark:hover:border-amber-800 dark:hover:text-amber-400 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                            title="Edit Transaksi"
                            aria-label="Edit Transaksi {{ $tx['transaction_number'] }}"
                        >
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                    @endif
                    @if($canAddItems && ($tx['is_proses'] ?? false))
                        <a
                            href="{{ route('transactions.index', ['q' => $tx['transaction_number']]) }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-emerald-200 dark:border-emerald-800/70 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 transition-colors"
                            title="Tambah Pesanan (Status Proses)"
                            aria-label="Tambah Pesanan {{ $tx['transaction_number'] }}"
                        >
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Footer --}}
    <div class="px-5 py-3.5 border-t border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
        <p>Menampilkan {{ count($transactions) }} transaksi terbaru</p>
        <a href="{{ route('transactions.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
            Lihat Semua Transaksi <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>
@endif
