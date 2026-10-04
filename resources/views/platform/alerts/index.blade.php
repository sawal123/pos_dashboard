<x-layouts::platform :title="'Alert Operasional'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Alert Operasional
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Deteksi kondisi dan anomali operasional server yang dihitung langsung dari data kanonikal secara real-time.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="bell" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Aktif: <strong class="text-slate-900 dark:text-white">{{ number_format($summary['total']) }}</strong> Alert</span>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            {{-- Total Alerts --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Alert</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            {{-- Critical --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Kritis</span>
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-rose-600 dark:text-rose-400">
                    {{ number_format($summary['critical']) }}
                </div>
            </div>

            {{-- Warning --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Peringatan</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-amber-600 dark:text-amber-400">
                    {{ number_format($summary['warning']) }}
                </div>
            </div>

            {{-- Info --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Informasi</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                        <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-sky-600 dark:text-sky-400">
                    {{ number_format($summary['info']) }}
                </div>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.alerts.index') }}" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                    {{-- Search query input --}}
                    <div class="lg:col-span-5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Cari bisnis, judul alert, target ID..."
                            class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                    </div>

                    {{-- Severity filter --}}
                    <div class="lg:col-span-3">
                        <select
                            name="severity"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                            <option value="all" {{ $filters['severity'] === 'all' ? 'selected' : '' }}>Semua Tingkat Keparahan</option>
                            <option value="critical" {{ $filters['severity'] === 'critical' ? 'selected' : '' }}>Kritis (Critical)</option>
                            <option value="warning" {{ $filters['severity'] === 'warning' ? 'selected' : '' }}>Peringatan (Warning)</option>
                            <option value="info" {{ $filters['severity'] === 'info' ? 'selected' : '' }}>Informasi (Info)</option>
                        </select>
                    </div>

                    {{-- Type filter --}}
                    <div class="lg:col-span-4">
                        <select
                            name="type"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                            <option value="all" {{ $filters['type'] === 'all' ? 'selected' : '' }}>Semua Tipe Alert</option>
                            @foreach($types as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}" {{ $filters['type'] === $typeKey ? 'selected' : '' }}>
                                    {{ $typeLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                        Menampilkan <strong>{{ count($alerts) }}</strong> dari <strong>{{ $summary['total'] }}</strong> alert aktif saat ini
                    </span>
                    <div class="flex items-center gap-2">
                        @if($filters['q'] !== '' || $filters['severity'] !== 'all' || $filters['type'] !== 'all')
                            <a
                                href="{{ route('platform.alerts.index') }}"
                                class="px-3 py-1.5 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            >
                                Reset Filter
                            </a>
                        @endif
                        <button
                            type="submit"
                            class="px-4 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Alert Cards List --}}
        @if(count($alerts) === 0)
            <div class="p-8 md:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-center">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
                <h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">
                    Tidak ada alert operasional aktif
                </h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    Semua langganan Cloud, rekonsiliasi pembayaran, kuota perangkat, dan cadangan database berada dalam kondisi normal tanpa anomali.
                </p>
                @if($filters['q'] !== '' || $filters['severity'] !== 'all' || $filters['type'] !== 'all')
                    <div class="mt-4">
                        <a
                            href="{{ route('platform.alerts.index') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                        >
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            Reset Kriteria Pencarian
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="space-y-3.5">
                @foreach($alerts as $alert)
                    @php
                        $severity = (string) $alert['severity'];
                        $isCritical = $severity === \App\Support\PlatformOperationalAlertType::SEVERITY_CRITICAL;
                        $isWarning = $severity === \App\Support\PlatformOperationalAlertType::SEVERITY_WARNING;

                        $cardBorder = $isCritical
                            ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50/20 dark:bg-rose-950/10'
                            : ($isWarning
                                ? 'border-amber-200 dark:border-amber-900/60 bg-amber-50/20 dark:bg-amber-950/10'
                                : 'border-sky-200 dark:border-sky-900/60 bg-sky-50/20 dark:bg-sky-950/10');

                        $badgeClasses = \App\Support\PlatformOperationalAlertType::severityBadgeClasses($severity);
                        $badgeLabel = \App\Support\PlatformOperationalAlertType::severityLabel($severity);
                        $typeLabel = \App\Support\PlatformOperationalAlertType::typeLabel((string) $alert['type']);
                    @endphp

                    <div class="p-4 md:p-5 rounded-2xl bg-white dark:bg-slate-900 border {{ $cardBorder }} shadow-xs transition-all hover:shadow-md">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="space-y-2 flex-1 min-w-0">
                                {{-- Badges Row --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold tracking-wide uppercase {{ $badgeClasses }}">
                                        @if($isCritical)
                                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                                        @elseif($isWarning)
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                        @else
                                            <i data-lucide="info" class="w-3 h-3"></i>
                                        @endif
                                        <span>{{ $badgeLabel }}</span>
                                    </span>

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $typeLabel }}
                                    </span>

                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                        <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <strong class="text-slate-800 dark:text-slate-200">{{ $alert['business_name'] }}</strong>
                                    </span>
                                </div>

                                {{-- Title --}}
                                <h3 class="text-sm md:text-base font-bold text-slate-900 dark:text-white">
                                    {{ $alert['title'] }}
                                </h3>

                                {{-- Description --}}
                                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ $alert['description'] }}
                                </p>

                                {{-- Meta information --}}
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="clock" class="w-3 h-3"></i>
                                        @if($alert['occurred_at'])
                                            <span>Waktu/Batas: <strong class="text-slate-700 dark:text-slate-300">{{ \Illuminate\Support\Carbon::parse($alert['occurred_at'])->format('d M Y, H:i') }}</strong></span>
                                        @else
                                            <span>Waktu/Batas: <strong class="text-slate-700 dark:text-slate-300">Kondisi saat ini</strong></span>
                                        @endif
                                    </span>

                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="tag" class="w-3 h-3"></i>
                                        <span>Target: <strong class="text-slate-700 dark:text-slate-300">{{ $alert['target_label'] }}</strong></span>
                                    </span>
                                </div>
                            </div>

                            {{-- Action Link --}}
                            <div class="sm:self-center shrink-0 pt-2 sm:pt-0">
                                <a
                                    href="{{ $alert['action_url'] }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 shadow-xs transition-colors"
                                >
                                    <span>Buka Detail</span>
                                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Definitions & Telemetry Limitations Panel --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 pt-2">
            {{-- Alert Definitions --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Tentang Alert Operasional
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Daftar jenis kondisi kanonikal dan sumber data kalkulasi.
                        </p>
                    </div>
                </div>

                <div class="space-y-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($definitions as $def)
                        <div class="pt-3 first:pt-0 space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $def['title'] }}</span>
                                <span class="text-[10px] font-bold uppercase {{ \App\Support\PlatformOperationalAlertType::severityBadgeClasses($def['severity']) }} px-2 py-0.5 rounded-md">
                                    {{ \App\Support\PlatformOperationalAlertType::severityLabel($def['severity']) }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                                {{ $def['condition'] }}
                            </p>
                            <div class="text-[10px] font-mono text-slate-400 dark:text-slate-500">
                                Sumber: {{ $def['source'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Telemetry Limitations --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Keterbatasan Telemetri Server
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Transparansi batasan data kanonikal saat ini.
                        </p>
                    </div>
                </div>

                <div class="space-y-3.5">
                    @foreach($limitations as $lim)
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-700/60 space-y-1">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200">
                                <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-amber-500"></i>
                                <span>{{ $lim['title'] }}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                {{ $lim['description'] }}
                            </p>
                        </div>
                    @endforeach

                    <div class="p-3 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-[11px] text-slate-600 dark:text-slate-300">
                        <strong class="font-bold text-indigo-700 dark:text-indigo-400">Prinsip Kanonikal:</strong> Alert operasional bersifat dinamis dan hanya dihitung saat diminta tanpa persistensi tabel alert. Begitu anomali diperbaiki atau kedaluwarsa diperpanjang, alert akan hilang secara otomatis.
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts::platform>
