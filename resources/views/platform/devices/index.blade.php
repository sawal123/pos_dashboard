<x-layouts::platform :title="'Perangkat'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Manajemen Perangkat Cloud
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Inventaris seluruh perangkat kasir, pengawasan kuota Cloud, dan kontrol akses operasional perangkat.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="smartphone" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($devices->total()) }}</strong> Perangkat</span>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-800 dark:text-emerald-300">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-3 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs font-medium text-rose-800 dark:text-rose-300">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('info'))
            <div class="flex items-center gap-3 p-4 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-xs font-medium text-sky-800 dark:text-sky-300">
                <i data-lucide="info" class="w-4 h-4 text-sky-600 shrink-0"></i>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            {{-- Total Devices --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Perangkat</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            {{-- Active Devices --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat Aktif</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ number_format($summary['active']) }}
                </div>
            </div>

            {{-- Inactive Devices --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat Nonaktif</span>
                    <span class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        <i data-lucide="slash" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-700 dark:text-slate-300">
                    {{ number_format($summary['inactive']) }}
                </div>
            </div>

            {{-- Businesses at Limit --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Bisnis di Batas Kuota</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-amber-600 dark:text-amber-400">
                    {{ number_format($summary['at_limit']) }}
                </div>
            </div>
        </div>

        {{-- Filter & Search Card --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.devices.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                {{-- Search query --}}
                <div class="lg:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $currentQ }}"
                        placeholder="Cari nama perangkat, identifier, nama bisnis, atau outlet..."
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                </div>

                {{-- Status Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="status"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">Semua Status</option>
                        <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>

                {{-- Entitlement Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="entitlement"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">Semua Entitlement</option>
                        <option value="cloud_active" {{ $currentEntitlement === 'cloud_active' ? 'selected' : '' }}>Akses Cloud Aktif</option>
                        <option value="cloud_denied" {{ $currentEntitlement === 'cloud_denied' ? 'selected' : '' }}>Akses Cloud Tidak Aktif</option>
                    </select>
                </div>

                {{-- Submit & Reset Buttons --}}
                <div class="lg:col-span-12 flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/80">
                    @if($currentQ || $currentStatus || $currentEntitlement)
                        <a
                            href="{{ route('platform.devices.index') }}"
                            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-400 transition-colors"
                        >
                            Reset
                        </a>
                    @endif
                    <button
                        type="submit"
                        class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                    >
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- Devices Table Card --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">Perangkat</th>
                            <th scope="col" class="px-4 py-3">Identifier</th>
                            <th scope="col" class="px-4 py-3">Bisnis</th>
                            <th scope="col" class="px-4 py-3">Outlet</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Terakhir Aktif</th>
                            <th scope="col" class="px-4 py-3">Terdaftar</th>
                            <th scope="col" class="px-4 py-3">Kuota Cloud</th>
                            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800">
                        @forelse($devices as $device)
                            @php
                                $business = $device->business;
                                $hasCloud = $business ? $business->hasCloudAccess() : false;
                                $activeForBusiness = $business ? ($activeCounts[$business->id] ?? 0) : 0;
                                $isFull = $limit > 0 && $activeForBusiness >= $limit;
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                {{-- Perangkat Name & Platform --}}
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $device->name }}
                                    </div>
                                    @if($device->platform)
                                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">
                                            {{ $device->platform }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Identifier --}}
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
                                        {{ $device->identifier }}
                                    </span>
                                </td>

                                {{-- Bisnis --}}
                                <td class="px-4 py-3">
                                    @if($business)
                                        <a
                                            href="{{ route('platform.businesses.show', $business) }}"
                                            class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            {{ $business->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400 italic">Tidak Diketahui</span>
                                    @endif
                                </td>

                                {{-- Outlet --}}
                                <td class="px-4 py-3">
                                    @if($device->outlet)
                                        <span class="font-medium text-slate-800 dark:text-slate-200">
                                            {{ $device->outlet->name }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Tidak terhubung ke outlet</span>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3">
                                    @if($device->status === \App\Models\Device::STATUS_ACTIVE)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Terakhir Aktif --}}
                                <td class="px-4 py-3">
                                    @if($device->last_seen_at)
                                        <div class="font-medium text-slate-700 dark:text-slate-300">
                                            {{ $device->last_seen_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $device->last_seen_at->diffForHumans() }}
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Belum pernah aktif</span>
                                    @endif
                                </td>

                                {{-- Terdaftar --}}
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {{ $device->registered_at ? $device->registered_at->timezone('Asia/Jakarta')->format('d M Y') : '-' }}
                                </td>

                                {{-- Kuota Cloud --}}
                                <td class="px-4 py-3">
                                    @if(! $hasCloud)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                            Cloud Tidak Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md font-mono text-[11px] font-bold {{ $isFull ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/80' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                                            <span>{{ $activeForBusiness }} / {{ $limit }}</span>
                                            @if($isFull)
                                                <span class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400">Penuh</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('platform.devices.show', $device) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors"
                                    >
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>Detail</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-3">
                                            <i data-lucide="smartphone" class="w-6 h-6"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Tidak ada perangkat ditemukan</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            @if($devices->hasPages())
                <div class="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    {{ $devices->links() }}
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
