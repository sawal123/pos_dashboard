@props([
    'devices' => [],
    'outlets' => [],
    'currentFilters' => [],
])

@php
    $datePreset = $currentFilters['date'] ?? 'all';
    $isCustomDate = $datePreset === 'custom';
@endphp

<form method="GET" action="{{ route('sync.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 sm:gap-4 items-center">
        {{-- Search Input --}}
        <div class="lg:col-span-4 relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                name="q"
                id="sync-search" 
                value="{{ $currentFilters['q'] ?? '' }}"
                placeholder="Cari request ID atau perangkat..." 
                class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
                aria-label="Cari request ID atau perangkat"
            >
        </div>

        {{-- Device Filter --}}
        <div class="lg:col-span-2">
            <select 
                name="device_id"
                id="sync-device-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
                aria-label="Filter perangkat"
            >
                <option value="">Semua Perangkat</option>
                @foreach($devices as $device)
                    <option value="{{ $device['id'] }}" @selected((string)($currentFilters['device_id'] ?? '') === (string)$device['id'])>
                        {{ $device['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Outlet Filter --}}
        <div class="lg:col-span-2">
            <select 
                name="outlet_id"
                id="sync-outlet-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
                aria-label="Filter outlet"
            >
                <option value="">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet['id'] }}" @selected((string)($currentFilters['outlet_id'] ?? '') === (string)$outlet['id'])>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Date Filter Preset --}}
        <div class="lg:col-span-2">
            <select 
                name="date"
                id="sync-date-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
                aria-label="Filter rentang tanggal"
            >
                <option value="all" @selected(($currentFilters['date'] ?? '') === 'all')>Semua Tanggal</option>
                <option value="today" @selected(($currentFilters['date'] ?? '') === 'today')>Hari Ini</option>
                <option value="7days" @selected(($currentFilters['date'] ?? '') === '7days')>7 Hari</option>
                <option value="30days" @selected(($currentFilters['date'] ?? '') === '30days')>30 Hari</option>
                <option value="custom" @selected($isCustomDate)>Periode Kustom</option>
            </select>
        </div>

        {{-- Action Buttons: Submit & Reset --}}
        <div class="lg:col-span-2 flex items-center justify-end gap-2">
            <button
                type="submit"
                id="sync-submit-filter"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span>Filter</span>
            </button>
            <a
                href="{{ route('sync.index') }}"
                id="sync-reset-filter"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700/80 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset</span>
            </a>
        </div>
    </div>

    {{-- Custom Date Range Section --}}
    <div id="sync-custom-date-container" class="{{ $isCustomDate ? '' : 'hidden' }} pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-center">
        <div>
            <label for="sync-start-date" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tanggal Mulai</label>
            <input 
                type="date" 
                name="start_date"
                id="sync-start-date" 
                value="{{ $currentFilters['start_date'] ?? '' }}"
                class="w-full px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
        </div>
        <div>
            <label for="sync-end-date" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tanggal Selesai</label>
            <input 
                type="date" 
                name="end_date"
                id="sync-end-date" 
                value="{{ $currentFilters['end_date'] ?? '' }}"
                class="w-full px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
        </div>
    </div>
</form>
