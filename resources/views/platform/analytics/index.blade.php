<x-layouts::platform :title="'Analitik Penggunaan Platform'">
    <div class="space-y-6">

        {{-- Page Heading & Filters --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Analitik Penggunaan Platform
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Metrik operasional agregat transaksi merchant, aktivitas sinkronisasi, dan perangkat lintas tenant.
                </p>
            </div>

            {{-- Period Filter Form --}}
            <form method="GET" action="{{ route('platform.analytics.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
                {{-- Preset Buttons / Dropdown --}}
                <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <button
                        type="submit"
                        name="date"
                        value="today"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === 'today' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Hari Ini
                    </button>
                    <button
                        type="submit"
                        name="date"
                        value="7days"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === '7days' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        7 Hari
                    </button>
                    <button
                        type="submit"
                        name="date"
                        value="30days"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === '30days' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        30 Hari
                    </button>
                </div>

                {{-- Custom Date Inputs --}}
                <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs">
                    <input
                        type="hidden"
                        name="date"
                        value="custom"
                    >
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $current_filters['start_date'] }}"
                        class="px-2 py-1 bg-transparent text-slate-700 dark:text-slate-200 text-xs border-0 focus:ring-1 focus:ring-indigo-500 rounded-lg"
                        placeholder="Mulai"
                        aria-label="Tanggal Mulai"
                    >
                    <span class="text-slate-400 text-xs">-</span>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $current_filters['end_date'] }}"
                        class="px-2 py-1 bg-transparent text-slate-700 dark:text-slate-200 text-xs border-0 focus:ring-1 focus:ring-indigo-500 rounded-lg"
                        placeholder="Selesai"
                        aria-label="Tanggal Selesai"
                    >
                    <button
                        type="submit"
                        class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition-colors"
                    >
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- Active Period Badge --}}
        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
            <div class="flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Periode Aktif: <strong>{{ $current_filters['period_label'] }}</strong> ({{ $current_filters['start_formatted'] }} s/d {{ $current_filters['end_formatted'] }})</span>
            </div>
            <span class="text-[11px] text-slate-400 dark:text-slate-500 hidden sm:inline">
                Sumber data: sold_at &amp; processed_at
            </span>
        </div>

        {{-- Primary KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
            {{-- Total Transaksi --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Transaksi</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total_transactions'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Status transaksi selesai
                </div>
            </div>

            {{-- Nilai Transaksi --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nilai Transaksi</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-lg md:text-xl font-black text-emerald-600 dark:text-emerald-400 truncate" title="{{ $summary['formatted_transaction_value'] }}">
                    {{ $summary['formatted_transaction_value'] }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Volume transaksi merchant
                </div>
            </div>

            {{-- Bisnis Bertransaksi --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Bisnis Bertransaksi</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                        <i data-lucide="store" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['businesses_with_transactions'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Aktif bertransaksi di periode
                </div>
            </div>

            {{-- Bisnis dengan Sync --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Bisnis dengan Sync</span>
                    <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['businesses_with_sync'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Kirim sync request server
                </div>
            </div>

            {{-- Perangkat Terlihat (30 Hari) --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Device Terlihat (30h)</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['devices_seen_30d'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Telemetri last_seen_at &ge; 30h
                </div>
            </div>
        </div>

        {{-- Transaction Trend Chart & Distribution Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: Daily Transaction Trend (Span 2) --}}
            <div class="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            Tren Transaksi Harian
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Jumlah transaksi selesai per hari dalam periode {{ $current_filters['period_label'] }}.
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">
                            {{ number_format($trend['total_count'], 0, ',', '.') }} Transaksi
                        </span>
                        <span class="block text-[10px] text-slate-400 dark:text-slate-500">
                            {{ $trend['formatted_total_amount'] }}
                        </span>
                    </div>
                </div>

                {{-- CSS / SVG Histogram Chart Bars --}}
                @if(count($trend['intervals']) > 0)
                    <div class="pt-4 pb-2">
                        <div class="h-44 flex items-end gap-1 sm:gap-1.5 overflow-x-auto pb-2" role="img" aria-label="Grafik Tren Transaksi Harian">
                            @foreach($trend['intervals'] as $item)
                                @php
                                    $heightPercent = $trend['max_count'] > 0
                                        ? max(4, (int) round(($item['count'] / $trend['max_count']) * 100))
                                        : 4;
                                @endphp
                                <div class="flex-1 min-w-[14px] sm:min-w-[20px] flex flex-col items-center justify-end h-full group relative">
                                    {{-- Tooltip Hover --}}
                                    <div class="absolute bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-20 pointer-events-none">
                                        <div class="bg-slate-900 dark:bg-slate-800 text-white text-[10px] rounded-lg px-2 py-1 shadow-lg whitespace-nowrap text-center">
                                            <p class="font-bold">{{ $item['label'] }}</p>
                                            <p>{{ $item['count'] }} transaksi</p>
                                            <p class="text-emerald-400 font-mono">{{ $item['formatted_amount'] }}</p>
                                        </div>
                                        <div class="w-1.5 h-1.5 bg-slate-900 dark:bg-slate-800 rotate-45 -mt-0.5"></div>
                                    </div>

                                    {{-- Bar --}}
                                    <div
                                        class="w-full rounded-t-md transition-all duration-300 {{ $item['count'] > 0 ? 'bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400' : 'bg-slate-100 dark:bg-slate-800' }}"
                                        style="height: {{ $heightPercent }}%;"
                                    ></div>

                                    {{-- Day Label (Show periodically to prevent clutter) --}}
                                    @if(count($trend['intervals']) <= 14 || $loop->first || $loop->last || $loop->iteration % 5 === 0)
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-1 whitespace-nowrap">
                                            {{ $item['short_label'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500">
                        Tidak ada data transaksi pada rentang periode yang dipilih.
                    </div>
                @endif
            </div>

            {{-- Right: Platform Usage & Subscription Breakdown --}}
            <div class="space-y-4">
                {{-- Distribusi Langganan --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Distribusi Langganan Bisnis
                    </h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-400">Cloud Aktif:</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $subscription_distribution['cloud_active'] }} Bisnis ({{ $subscription_distribution['cloud_percentage'] }}%)
                            </span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $subscription_distribution['cloud_percentage'] }}%;"></div>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1">
                            <span class="text-slate-600 dark:text-slate-400">Free / Non-Cloud:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">
                                {{ $subscription_distribution['non_cloud'] }} Bisnis
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Total Terdaftar:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ $subscription_distribution['total_businesses'] }} Bisnis
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Aktivitas Sinkronisasi --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                        Aktivitas Sinkronisasi Periode Ini
                    </h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 dark:text-slate-400">Total Sync Requests:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ number_format($sync_overview['total_sync_requests'], 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 dark:text-slate-400">Bisnis yang Sinkron:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ number_format($sync_overview['businesses_with_sync'], 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 dark:text-slate-400">Perangkat Melakukan Sync:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ number_format($sync_overview['devices_with_sync'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Keanggotaan Bisnis (Members) --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4 text-sky-600 dark:text-sky-400"></i>
                        Anggota Bisnis (Merchant Members)
                    </h3>
                    <div class="flex items-baseline justify-between pt-1">
                        <span class="text-xl font-black text-slate-900 dark:text-white">
                            {{ number_format($members_overview['total_merchant_members'], 0, ',', '.') }}
                        </span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            Total relasi akun merchant
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-normal">
                        Dihitung dari relasi keanggotaan bisnis terdaftar. Bukan metrik aktivitas pengguna.
                    </p>
                </div>
            </div>

        </div>

        {{-- Top Businesses by Transaction Activity Table --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
                        Bisnis dengan Aktivitas Transaksi Tertinggi
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Top 10 merchant berdasarkan volume dan nilai transaksi pada periode {{ $current_filters['period_label'] }}.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">Bisnis</th>
                            <th scope="col" class="px-4 py-3">Tipe Bisnis</th>
                            <th scope="col" class="px-4 py-3 text-right">Jumlah Transaksi</th>
                            <th scope="col" class="px-4 py-3 text-right">Nilai Transaksi</th>
                            <th scope="col" class="px-4 py-3">Transaksi Terakhir</th>
                            <th scope="col" class="px-4 py-3">Status Cloud</th>
                            <th scope="col" class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800">
                        @forelse($top_businesses as $item)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ $item['business_name'] }}
                                    </div>
                                    @if($item['business_slug'] !== '')
                                        <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                                            {{ $item['business_slug'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-[11px]">
                                    {{ $item['business_type'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">
                                    {{ number_format($item['transaction_count'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                    {{ $item['formatted_value'] }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                                    {{ $item['formatted_last_transaction'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($item['has_cloud_access'])
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                                            <i data-lucide="sparkles" class="w-3 h-3 text-indigo-500"></i>
                                            {{ $item['cloud_status_label'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                            {{ $item['cloud_status_label'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <a
                                        href="{{ route('platform.businesses.show', $item['business_id']) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/50 transition-colors"
                                    >
                                        <span>Detail</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                                    Belum ada transaksi merchant yang tercatat pada rentang periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Disclosures, Domain Boundaries & Metric Notes --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                Tentang Metrik &amp; Batasan Domain Analitik
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($metric_notes as $note)
                    <div class="p-3.5 rounded-xl bg-slate-50/75 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800 text-xs space-y-1">
                        <span class="font-bold text-slate-900 dark:text-white text-[11px] block">
                            {{ $note['title'] }}
                        </span>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ $note['description'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-layouts::platform>
