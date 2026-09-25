@props(['filterOptions', 'currentFilters'])

<form
    action="{{ route('laundry-orders.index') }}"
    method="GET"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4 space-y-4"
>
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-3">
        <div>
            <label for="laundrySearch" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Nomor / Pelanggan
            </label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input
                    id="laundrySearch"
                    name="q"
                    type="search"
                    maxlength="100"
                    value="{{ $currentFilters['q'] ?? '' }}"
                    placeholder="Cari nomor transaksi, nama, atau telepon"
                    class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>
        </div>

        <div>
            <label for="laundryOrderStatusFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Status Pengerjaan
            </label>
            <select
                id="laundryOrderStatusFilter"
                name="order_status"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all">Semua Pengerjaan</option>
                @foreach(($filterOptions['order_statuses'] ?? []) as $status)
                    <option value="{{ $status['value'] }}" @selected(($currentFilters['order_status'] ?? 'all') === $status['value'])>
                        {{ $status['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="laundryPaymentStatusFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Status Pembayaran
            </label>
            <select
                id="laundryPaymentStatusFilter"
                name="payment_status"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all" @selected(($currentFilters['payment_status'] ?? 'all') === 'all')>Semua Pembayaran</option>
                @foreach(($filterOptions['payment_statuses'] ?? []) as $paymentStatus)
                    <option value="{{ $paymentStatus['value'] }}" @selected(($currentFilters['payment_status'] ?? 'all') === $paymentStatus['value'])>
                        {{ $paymentStatus['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="laundryOutletFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Outlet
            </label>
            <select
                id="laundryOutletFilter"
                name="outlet_id"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Outlet</option>
                @foreach(($filterOptions['outlets'] ?? []) as $outlet)
                    <option value="{{ $outlet['id'] }}" @selected((string) ($currentFilters['outlet_id'] ?? '') === (string) $outlet['id'])>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="laundryDateFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Periode
            </label>
            <select
                id="laundryDateFilter"
                name="date"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all" @selected(($currentFilters['date'] ?? 'all') === 'all')>Semua Periode</option>
                <option value="today" @selected(($currentFilters['date'] ?? 'all') === 'today')>Hari Ini</option>
                <option value="7d" @selected(($currentFilters['date'] ?? 'all') === '7d')>7 Hari</option>
                <option value="30d" @selected(($currentFilters['date'] ?? 'all') === '30d')>30 Hari</option>
                <option value="custom" @selected(($currentFilters['date'] ?? 'all') === 'custom')>Custom</option>
            </select>
        </div>
    </div>

    <div id="laundryCustomDateContainer" class="{{ ($currentFilters['date'] ?? 'all') === 'custom' ? '' : 'hidden' }} grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
        <div>
            <label for="laundryStartDate" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Dari</label>
            <input
                id="laundryStartDate"
                name="start_date"
                type="date"
                value="{{ $currentFilters['start_date'] ?? '' }}"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
        <div>
            <label for="laundryEndDate" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Sampai</label>
            <input
                id="laundryEndDate"
                name="end_date"
                type="date"
                value="{{ $currentFilters['end_date'] ?? '' }}"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label for="laundryOverdueFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Ketepatan Waktu
            </label>
            <select
                id="laundryOverdueFilter"
                name="overdue"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all" @selected(($currentFilters['overdue'] ?? 'all') === 'all')>Semua Pesanan</option>
                <option value="overdue" @selected(($currentFilters['overdue'] ?? 'all') === 'overdue')>Terlambat</option>
                <option value="ontime" @selected(($currentFilters['overdue'] ?? 'all') === 'ontime')>Tepat Waktu</option>
            </select>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-end">
        <a
            href="{{ route('laundry-orders.index') }}"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            Reset
        </a>
        <button
            type="submit"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="filter" class="w-4 h-4"></i>
            Terapkan
        </button>
    </div>
</form>
