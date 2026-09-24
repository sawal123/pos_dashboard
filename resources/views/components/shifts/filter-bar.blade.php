@props(['filterOptions', 'currentFilters'])

<form
    action="{{ route('shifts.index') }}"
    method="GET"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4 space-y-4"
>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <label for="shiftSearch" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Nomor Shift
            </label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input
                    id="shiftSearch"
                    name="q"
                    type="search"
                    value="{{ $currentFilters['q'] ?? '' }}"
                    placeholder="Cari nomor shift"
                    class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>
        </div>

        <div>
            <label for="shiftOutletFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Outlet
            </label>
            <select
                id="shiftOutletFilter"
                name="outlet_id"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Outlet</option>
                @foreach(($filterOptions['outlets'] ?? []) as $outlet)
                    <option value="{{ $outlet['id'] }}" @selected(($currentFilters['outlet_id'] ?? '') == $outlet['id'])>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="shiftStatusFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Status
            </label>
            <select
                id="shiftStatusFilter"
                name="status"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="all">Semua Status</option>
                @foreach(($filterOptions['statuses'] ?? []) as $status)
                    <option value="{{ $status['value'] }}" @selected(($currentFilters['status'] ?? 'all') === $status['value'])>
                        {{ $status['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="shiftDateFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Periode
            </label>
            <select
                id="shiftDateFilter"
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

    <div id="shiftsCustomDateContainer" class="{{ ($currentFilters['date'] ?? 'all') === 'custom' ? '' : 'hidden' }} grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
        <div>
            <label for="shiftStartDate" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Dari</label>
            <input
                id="shiftStartDate"
                name="start_date"
                type="date"
                value="{{ $currentFilters['start_date'] ?? '' }}"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
        <div>
            <label for="shiftEndDate" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Sampai</label>
            <input
                id="shiftEndDate"
                name="end_date"
                type="date"
                value="{{ $currentFilters['end_date'] ?? '' }}"
                class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-end">
        <a
            href="{{ route('shifts.index') }}"
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
