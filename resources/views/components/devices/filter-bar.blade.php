@props([
    'outlets' => [],
    'platforms' => [],
    'currentFilters' => [],
])

<form method="GET" action="{{ route('devices.index') }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
        {{-- Search Input (lg:col-span-4) --}}
        <div class="sm:col-span-2 lg:col-span-4 relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </div>
            <input
                type="text"
                name="q"
                id="searchDeviceInput"
                value="{{ $currentFilters['q'] ?? '' }}"
                placeholder="Cari nama atau identifier perangkat..."
                class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Cari nama atau identifier perangkat"
            >
        </div>

        {{-- Outlet Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                name="outlet_id"
                id="filterDeviceOutlet"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter outlet perangkat"
            >
                <option value="all">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet['id'] }}" @selected((string)($currentFilters['outlet_id'] ?? '') === (string)$outlet['id'])>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Status Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                name="status"
                id="filterDeviceStatus"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter status perangkat"
            >
                <option value="all">Semua Status</option>
                <option value="active" @selected(($currentFilters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($currentFilters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        {{-- Platform Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                name="platform"
                id="filterDevicePlatform"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter platform perangkat"
            >
                <option value="all">Semua Platform</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform }}" @selected(($currentFilters['platform'] ?? '') === $platform)>
                        {{ $platform }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Action Buttons: Submit & Reset --}}
        <div class="lg:col-span-2 flex items-center justify-end gap-2">
            <button
                type="submit"
                id="filterDeviceSubmitBtn"
                class="flex-1 py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                <span>Filter</span>
            </button>
            <a
                href="{{ route('devices.index') }}"
                id="resetDeviceFilterBtn"
                class="py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset</span>
            </a>
        </div>
    </div>
</form>
