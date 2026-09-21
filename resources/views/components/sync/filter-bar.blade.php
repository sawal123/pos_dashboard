@props(['devices' => [], 'outlets' => []])

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
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
                id="sync-search" 
                placeholder="Cari request ID atau perangkat..." 
                class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
            >
        </div>

        {{-- Device Filter --}}
        <div class="lg:col-span-2">
            <select 
                id="sync-device-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
            >
                <option value="">Semua Perangkat</option>
                @foreach($devices as $device)
                    <option value="{{ $device['id'] }}">{{ $device['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Outlet Filter --}}
        <div class="lg:col-span-2">
            <select 
                id="sync-outlet-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
            >
                <option value="">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    @php
                        $outletVal = is_array($outlet) ? ($outlet['name'] ?? '') : $outlet;
                    @endphp
                    <option value="{{ $outletVal }}">{{ $outletVal }}</option>
                @endforeach
            </select>
        </div>

        {{-- Date Filter Preset --}}
        <div class="lg:col-span-2">
            <select 
                id="sync-date-filter" 
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors"
            >
                <option value="">Semua Tanggal</option>
                <option value="today">Hari Ini</option>
                <option value="7days">7 Hari</option>
                <option value="30days">30 Hari</option>
                <option value="custom">Periode Kustom</option>
            </select>
        </div>

        {{-- Reset Button --}}
        <div class="lg:col-span-2 flex justify-end">
            <button 
                type="button" 
                id="sync-reset-filter" 
                class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700/80 rounded-xl transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset Filter</span>
            </button>
        </div>
    </div>

    {{-- Custom Date Range Section --}}
    <div id="sync-custom-date-container" class="hidden pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-center">
        <div>
            <label for="sync-start-date" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tanggal Mulai</label>
            <input 
                type="date" 
                id="sync-start-date" 
                class="w-full px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
        </div>
        <div>
            <label for="sync-end-date" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tanggal Selesai</label>
            <input 
                type="date" 
                id="sync-end-date" 
                class="w-full px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
        </div>
    </div>
</div>
