@props([
    'outlets' => [],
    'paymentMethods' => [],
    'statuses' => [],
    'currentFilters' => [],
])

@php
    $cf = $currentFilters;
    $isCustomDate = ($cf['date'] ?? 'all') === 'custom';
@endphp

<div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3.5">
    <form
        method="GET"
        action="{{ route('transactions.index') }}"
        id="transactionsFilterForm"
        class="space-y-3.5"
    >
        {{-- Top Row: Search & Export/Print Actions --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            {{-- Search Input --}}
            <div class="relative flex-1 max-w-md">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input
                    type="text"
                    id="searchTransactionsInput"
                    name="q"
                    value="{{ $cf['q'] ?? '' }}"
                    placeholder="Cari nomor transaksi / pelanggan..."
                    class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
            </div>

            {{-- Actions: Cetak & Ekspor --}}
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    id="printTransactionsBtn"
                    onclick="showToast('info', 'Fitur cetak akan tersedia setelah integrasi data.')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Cetak rekap transaksi"
                >
                    <i data-lucide="printer" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
                    <span>Cetak</span>
                </button>

                <button
                    type="button"
                    id="exportTransactionsBtn"
                    onclick="showToast('info', 'Fitur ekspor akan tersedia setelah integrasi data.')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Ekspor rekap transaksi"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
                    <span>Ekspor</span>
                </button>
            </div>
        </div>

        {{-- Bottom Row: Select Filters & Reset Filter --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 pt-2 border-t border-slate-100 dark:border-slate-800/80">
            {{-- 1. Tanggal --}}
            <div>
                <label for="filterDate" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Tanggal</label>
                <select
                    id="filterDate"
                    name="date"
                    class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all" @selected(($cf['date'] ?? 'all') === 'all')>Semua Tanggal</option>
                    <option value="today" @selected(($cf['date'] ?? '') === 'today')>Hari Ini</option>
                    <option value="7days" @selected(($cf['date'] ?? '') === '7days')>7 Hari</option>
                    <option value="30days" @selected(($cf['date'] ?? '') === '30days')>30 Hari</option>
                    <option value="custom" @selected(($cf['date'] ?? '') === 'custom')>Periode Kustom</option>
                </select>
            </div>

            {{-- 2. Outlet --}}
            <div>
                <label for="filterOutlet" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Outlet</label>
                <select
                    id="filterOutlet"
                    name="outlet_id"
                    class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all">Semua Outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet['id'] }}" @selected((string)($cf['outlet_id'] ?? '') === (string)$outlet['id'])>
                            {{ $outlet['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Metode Pembayaran --}}
            <div>
                <label for="filterPaymentMethod" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Metode</label>
                <select
                    id="filterPaymentMethod"
                    name="payment_method"
                    class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all">Semua Metode</option>
                    @foreach($paymentMethods as $method)
                        @php
                            $val = is_array($method) ? $method['value'] : $method;
                            $lbl = is_array($method) ? $method['label'] : match(strtolower((string) $method)) {
                                'cash', 'tunai' => 'Tunai',
                                'qris' => 'QRIS',
                                'transfer' => 'Transfer',
                                'card', 'kartu' => 'Kartu',
                                default => ucwords(str_replace('_', ' ', (string) $method)),
                            };
                        @endphp
                        <option value="{{ $val }}" @selected(($cf['payment_method'] ?? '') === $val)>
                            {{ $lbl }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 4. Status Pembayaran --}}
            <div>
                <label for="filterPaymentStatus" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Status Bayar</label>
                <select
                    id="filterPaymentStatus"
                    name="payment_status"
                    class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all">Semua Status</option>
                    <option value="paid" @selected(($cf['payment_status'] ?? '') === 'paid')>Lunas</option>
                    <option value="unpaid" @selected(($cf['payment_status'] ?? '') === 'unpaid')>Belum Lunas</option>
                </select>
            </div>

            {{-- 5. Status Transaksi --}}
            <div>
                <label for="filterTransactionStatus" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Status Transaksi</label>
                <select
                    id="filterTransactionStatus"
                    name="status"
                    class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all">Semua Transaksi</option>
                    @foreach($statuses as $txStatus)
                        <option value="{{ $txStatus }}" @selected(($cf['status'] ?? '') === $txStatus)>
                            {{ match($txStatus) {
                                'completed' => 'Selesai',
                                'cancelled', 'canceled' => 'Dibatalkan',
                                default => ucwords(str_replace('_', ' ', $txStatus)),
                            } }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 6. Actions: Apply & Reset --}}
            <div class="flex items-end gap-1.5">
                <button
                    type="submit"
                    class="flex-1 py-1.5 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors flex items-center justify-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="search" class="w-3 h-3"></i>
                    <span>Terapkan</span>
                </button>
                <a
                    href="{{ route('transactions.index') }}"
                    id="resetFilterBtn"
                    class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors flex items-center justify-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Reset semua filter"
                >
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
                </a>
            </div>
        </div>

        {{-- Custom Date Range Inputs (Only shown when "Periode Kustom" is selected) --}}
        <div id="customDateRangeContainer" class="{{ $isCustomDate ? '' : 'hidden' }} pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-3">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-indigo-500"></i>
                <span>Rentang Tanggal:</span>
            </span>
            <div class="flex items-center gap-2">
                <label for="filterStartDate" class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Mulai</label>
                <input
                    type="date"
                    id="filterStartDate"
                    name="start_date"
                    value="{{ $cf['start_date'] ?? '' }}"
                    class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div class="flex items-center gap-2">
                <label for="filterEndDate" class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Selesai</label>
                <input
                    type="date"
                    id="filterEndDate"
                    name="end_date"
                    value="{{ $cf['end_date'] ?? '' }}"
                    class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
        </div>
    </form>
</div>
