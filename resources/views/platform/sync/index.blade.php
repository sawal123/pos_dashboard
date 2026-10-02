<x-layouts::platform :title="'Sinkronisasi'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Monitoring Sinkronisasi
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Observabilitas riwayat sync push yang berhasil committed ke server lintas seluruh bisnis dan perangkat.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="refresh-cw" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($requests->total()) }}</strong> Committed</span>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            {{-- Total Committed Requests --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Committed</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total_requests']) }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Hari ini: {{ number_format($summary['requests_today']) }} request
                </div>
            </div>

            {{-- Businesses with Sync --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Bisnis Terhubung</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['businesses_with_sync']) }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Memiliki riwayat sync committed
                </div>
            </div>

            {{-- Devices with Sync --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat Terhubung</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['devices_with_sync']) }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Tercatat pernah sync push
                </div>
            </div>

            {{-- Last Processed --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Terakhir Diproses</span>
                    <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-sm md:text-base font-bold text-slate-900 dark:text-white truncate" title="{{ $summary['last_processed'] }}">
                    {{ $summary['last_processed'] }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Waktu committed push server
                </div>
            </div>
        </div>

        {{-- Informational Observability Notice --}}
        <div class="flex items-start gap-3 p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200/60 dark:border-indigo-900/40 text-xs text-indigo-900 dark:text-indigo-200">
            <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
            <div class="space-y-1">
                <span class="font-bold">Observabilitas Sinkronisasi:</span>
                <p class="text-indigo-800/90 dark:text-indigo-300 text-[11px] leading-relaxed">
                    Server mencatat sync push yang berhasil committed untuk bukti idempotensi dan integritas data. Upaya sync yang terputus di jaringan sebelum committed tidak memiliki baris di tabel server. Pending queue lokal dikelola oleh POS Mobile masing-masing dan tidak disimpan pada database server.
                </p>
            </div>
        </div>

        {{-- Filter & Search Card --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.sync.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                {{-- Search query --}}
                <div class="lg:col-span-4 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $currentFilters['q'] }}"
                        placeholder="Cari request ID, perangkat, bisnis, atau outlet..."
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                </div>

                {{-- Business Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="business_id"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">Semua Bisnis</option>
                        @foreach($businesses as $b)
                            <option value="{{ $b['id'] }}" {{ (string) $currentFilters['business_id'] === (string) $b['id'] ? 'selected' : '' }}>
                                {{ $b['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Device Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="device_id"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="">Semua Perangkat</option>
                        @foreach($devices as $d)
                            <option value="{{ $d['id'] }}" {{ (string) $currentFilters['device_id'] === (string) $d['id'] ? 'selected' : '' }}>
                                {{ $d['name'] }} ({{ $d['identifier'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Date Filter Preset --}}
                <div class="lg:col-span-2">
                    <select
                        name="date"
                        id="datePresetSelect"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="all" {{ $currentFilters['date'] === 'all' ? 'selected' : '' }}>Semua Waktu</option>
                        <option value="today" {{ $currentFilters['date'] === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="7days" {{ $currentFilters['date'] === '7days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                        <option value="30days" {{ $currentFilters['date'] === '30days' ? 'selected' : '' }}>30 Hari Terakhir</option>
                        <option value="custom" {{ $currentFilters['date'] === 'custom' ? 'selected' : '' }}>Kustom Tanggal</option>
                    </select>
                </div>

                {{-- Custom Date Range (Conditionally shown or standard input row) --}}
                <div id="customDateRow" class="lg:col-span-12 grid grid-cols-1 sm:grid-cols-2 gap-3 {{ $currentFilters['date'] === 'custom' ? '' : 'hidden' }} pt-2 border-t border-slate-100 dark:border-slate-800/80">
                    <div>
                        <label for="startDateInput" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Tanggal Mulai</label>
                        <input
                            type="date"
                            id="startDateInput"
                            name="start_date"
                            value="{{ $currentFilters['start_date'] }}"
                            class="w-full px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                    </div>
                    <div>
                        <label for="endDateInput" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Tanggal Akhir</label>
                        <input
                            type="date"
                            id="endDateInput"
                            name="end_date"
                            value="{{ $currentFilters['end_date'] }}"
                            class="w-full px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="lg:col-span-12 flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/80">
                    @if($currentFilters['q'] || $currentFilters['business_id'] || $currentFilters['device_id'] || $currentFilters['outlet_id'] || $currentFilters['date'] !== 'all')
                        <a
                            href="{{ route('platform.sync.index') }}"
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

        {{-- Sync Requests Table Card --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">Diproses Pada</th>
                            <th scope="col" class="px-4 py-3">Bisnis</th>
                            <th scope="col" class="px-4 py-3">Perangkat</th>
                            <th scope="col" class="px-4 py-3">Identifier</th>
                            <th scope="col" class="px-4 py-3">Outlet</th>
                            <th scope="col" class="px-4 py-3">Request ID</th>
                            <th scope="col" class="px-4 py-3">Status Device</th>
                            <th scope="col" class="px-4 py-3">Terakhir Dilihat</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800">
                        @forelse($requests as $req)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Processed At --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-slate-900 dark:text-white">
                                        {{ $req['processed_at'] }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $req['processed_at_raw'] }}
                                    </div>
                                </td>

                                {{-- Business --}}
                                <td class="px-4 py-3">
                                    <a
                                        href="{{ route('platform.businesses.show', $req['business_id']) }}"
                                        class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >
                                        {{ $req['business_name'] }}
                                    </a>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $req['business_slug'] }}
                                    </div>
                                </td>

                                {{-- Device Name --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($req['device_id'])
                                        <a
                                            href="{{ route('platform.devices.show', $req['device_id']) }}"
                                            class="font-semibold text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        >
                                            {{ $req['device_name'] }}
                                        </a>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 italic">{{ $req['device_name'] }}</span>
                                    @endif
                                </td>

                                {{-- Identifier --}}
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                    {{ $req['device_identifier'] }}
                                </td>

                                {{-- Outlet --}}
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                    {{ $req['outlet_name'] }}
                                </td>

                                {{-- Request ID --}}
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-[11px] text-slate-700 dark:text-slate-300" title="{{ $req['request_id'] }}">
                                    {{ Str::limit($req['request_id'], 18) }}
                                </td>

                                {{-- Device Status --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($req['device_status'] === 'active')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @elseif($req['device_status'] === 'inactive')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            <span class="w-1 h-1 rounded-full bg-slate-400"></span>
                                            Nonaktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                            Tidak Terhubung
                                        </span>
                                    @endif
                                </td>

                                {{-- Last Seen --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-[11px] text-slate-800 dark:text-slate-200">
                                        {{ $req['last_seen_at'] }}
                                    </div>
                                    <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[9px] font-medium {{ $req['activity']['badge_class'] }}">
                                        {{ $req['activity']['label'] }}
                                    </span>
                                </td>

                                {{-- Status (Committed) --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                                        <i data-lucide="check" class="w-3 h-3 text-indigo-500"></i>
                                        Committed
                                    </span>
                                </td>

                                {{-- Action --}}
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <a
                                        href="{{ route('platform.sync.show', $req['id']) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors"
                                        title="Lihat Detail Request"
                                    >
                                        <span>Detail</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="p-3 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-3">
                                            <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                                        </div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                            Belum Ada Riwayat Sinkronisasi
                                        </p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-sm">
                                            Tidak ada data sync request yang sesuai dengan kriteria filter saat ini.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            @if($requests->hasPages())
                <div class="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var dateSelect = document.getElementById('datePresetSelect');
            var customRow = document.getElementById('customDateRow');
            if (dateSelect && customRow) {
                dateSelect.addEventListener('change', function () {
                    if (this.value === 'custom') {
                        customRow.classList.remove('hidden');
                    } else {
                        customRow.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</x-layouts::platform>
