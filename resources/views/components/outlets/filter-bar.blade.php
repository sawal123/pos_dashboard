@props(['filterOptions', 'currentFilters'])

<form
    action="{{ route('outlets.index') }}"
    method="GET"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4 space-y-4"
>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label for="outletSearch" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Nama atau Kode Outlet
            </label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input
                    id="outletSearch"
                    name="q"
                    type="search"
                    value="{{ $currentFilters['q'] ?? '' }}"
                    placeholder="Cari nama atau kode outlet"
                    class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>
        </div>

        <div>
            <label for="outletStatusFilter" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                Status
            </label>
            <select
                id="outletStatusFilter"
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
    </div>

    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-end">
        <a
            href="{{ route('outlets.index') }}"
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
