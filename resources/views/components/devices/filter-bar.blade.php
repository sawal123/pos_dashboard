@props([
    'outlets' => [],
    'platforms' => [],
])

<div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
        {{-- Search Input (lg:col-span-4) --}}
        <div class="sm:col-span-2 lg:col-span-4 relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </div>
            <input
                type="text"
                id="searchDeviceInput"
                placeholder="Cari nama atau identifier perangkat..."
                class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Cari nama atau identifier perangkat"
            >
        </div>

        {{-- Outlet Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                id="filterDeviceOutlet"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter outlet perangkat"
            >
                <option value="all">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet }}">{{ $outlet }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                id="filterDeviceStatus"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter status perangkat"
            >
                <option value="all">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>

        {{-- Platform Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                id="filterDevicePlatform"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter platform perangkat"
            >
                <option value="all">Semua Platform</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform }}">{{ $platform }}</option>
                @endforeach
            </select>
        </div>

        {{-- Action Buttons: Reset & Register Placeholder (lg:col-span-2) --}}
        <div class="lg:col-span-2 flex items-center justify-end gap-2">
            <button
                type="button"
                id="resetDeviceFilterBtn"
                class="w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset</span>
            </button>
        </div>
    </div>
</div>
