@props([
    'filterOptions' => [],
    'currentFilters' => [],
])

@php
    $cf = $currentFilters;
    $statuses = $filterOptions['statuses'] ?? [];
@endphp

<div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3.5">
    <form
        method="GET"
        action="{{ route('customers.index') }}"
        id="customersFilterForm"
        class="space-y-3.5"
    >
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
            <div class="relative flex-1 max-w-md">
                <label for="searchCustomersInput" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    Cari Pelanggan
                </label>
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 bottom-2.5 pointer-events-none"></i>
                <input
                    type="text"
                    id="searchCustomersInput"
                    name="q"
                    value="{{ $cf['q'] ?? '' }}"
                    placeholder="Cari nama, telepon atau email..."
                    class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                />
            </div>

            <div class="w-full md:w-56">
                <label for="customerStatusFilter" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    Status
                </label>
                <select
                    id="customerStatusFilter"
                    name="status"
                    class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 text-xs sm:text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                >
                    <option value="all" @selected(($cf['status'] ?? 'all') === 'all')>Semua Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status['value'] }}" @selected(($cf['status'] ?? 'all') === $status['value'])>
                            {{ $status['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <button
                    type="submit"
                    class="flex-1 md:flex-none py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Terapkan</span>
                </button>
                <a
                    href="{{ route('customers.index') }}"
                    id="resetCustomerFilterBtn"
                    class="py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Reset semua filter pelanggan"
                >
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
                    <span>Reset</span>
                </a>
            </div>
        </div>
    </form>
</div>
