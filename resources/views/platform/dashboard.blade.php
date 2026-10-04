<x-layouts::platform :title="'Overview'">
    <div class="space-y-6">

        {{-- Page Heading & Banner --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 p-6 md:p-8 text-white border border-indigo-800/40 shadow-xl shadow-indigo-950/20">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        ADMIN-01 &bull; Platform Foundation
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white">
                        Platform Admin Overview
                    </h1>
                    <p class="text-sm text-indigo-200/90 leading-relaxed">
                        Pusat kendali dan ringkasan global ekosistem NexaPOS secara real-time. Informasi agregasi lintas bisnis, akun pengguna, langganan aktif, dan perangkat POS yang terhubung.
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-3">
                    <div class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-md border border-white/15 text-right">
                        <p class="text-[11px] font-medium text-indigo-200 uppercase tracking-wider">Role Access</p>
                        <p class="text-sm font-bold text-white flex items-center justify-end gap-1.5 mt-0.5">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                            Super Admin
                        </p>
                    </div>
                </div>
            </div>

            {{-- Decorative Background Accents --}}
            <div class="absolute -right-12 -bottom-12 w-64 h-64 rounded-full bg-indigo-600/20 blur-3xl pointer-events-none"></div>
            <div class="absolute right-1/3 -top-12 w-48 h-48 rounded-full bg-purple-600/15 blur-2xl pointer-events-none"></div>
        </div>

        {{-- Section 1: Primary Metrics Grid (4 Main KPI Cards) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">

            {{-- Metric Card 1: Total Bisnis --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between transition-all hover:border-indigo-300 dark:hover:border-indigo-800">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                            <i data-lucide="building-2" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60" data-testid="platform-recent-businesses">
                            +{{ number_format($businesses['recent_30d']) }} (30h)
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Total Bisnis
                    </h2>
                    <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight" data-testid="platform-total-businesses">
                        {{ number_format($businesses['total']) }}
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Aktif: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-active-businesses">{{ number_format($businesses['active']) }}</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                        Nonaktif: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-inactive-businesses">{{ number_format($businesses['inactive']) }}</strong>
                    </span>
                </div>
            </div>

            {{-- Metric Card 2: Total User --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between transition-all hover:border-indigo-300 dark:hover:border-indigo-800">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60" data-testid="platform-recent-users">
                            +{{ number_format($users['recent_30d']) }} (30h)
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Total Pengguna
                    </h2>
                    <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight" data-testid="platform-total-users">
                        {{ number_format($users['total']) }}
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5" title="User terhubung ke minimal satu bisnis">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            User Bisnis:
                        </span>
                        <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-business-users">{{ number_format($users['business_users']) }}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5" title="Operator Platform Admin">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            Platform Admin:
                        </span>
                        <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-admin-users">{{ number_format($users['platform_admins']) }}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5" title="Pengguna terdaftar tanpa relasi bisnis">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            Belum Terhubung:
                        </span>
                        <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-unconnected-users">{{ number_format($users['unconnected']) }}</strong>
                    </div>
                </div>
            </div>

            {{-- Metric Card 3: Subscription --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between transition-all hover:border-indigo-300 dark:hover:border-indigo-800">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                            <i data-lucide="credit-card" class="w-5 h-5"></i>
                        </span>
                        <div class="flex items-center gap-1 text-[11px] font-semibold">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60" data-testid="platform-sub-active">
                                {{ number_format($subscriptions['statuses']['active']) }} Aktif
                            </span>
                        </div>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Langganan Bisnis
                    </h2>
                    <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight" data-testid="platform-total-subscriptions">
                        {{ number_format($subscriptions['total']) }}
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        Free: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-plan-free">{{ number_format($subscriptions['plans']['free']) }}</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Cloud: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-plan-cloud">{{ number_format($subscriptions['plans']['cloud']) }}</strong>
                    </span>
                </div>
            </div>

            {{-- Metric Card 4: Total Device --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between transition-all hover:border-indigo-300 dark:hover:border-indigo-800">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            <i data-lucide="tablet" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800/60" data-testid="platform-recent-devices">
                            {{ number_format($devices['recent_seen_30d']) }} Terlihat (30h)
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                        Total Perangkat POS
                    </h2>
                    <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight" data-testid="platform-total-devices">
                        {{ number_format($devices['total']) }}
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Aktif: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-active-devices">{{ number_format($devices['active']) }}</strong>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                        Nonaktif: <strong class="text-slate-800 dark:text-slate-200 font-semibold" data-testid="platform-inactive-devices">{{ number_format($devices['inactive']) }}</strong>
                    </span>
                </div>
            </div>

        </div>

        {{-- Section: Operational Alerts (ADMIN-14) --}}
        <div class="space-y-3.5" data-testid="platform-operational-alerts-section">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                        <i data-lucide="bell" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Alert Operasional</span>
                            @if(($alertsSummary['total'] ?? 0) > 0)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300">
                                    {{ $alertsSummary['total'] }}
                                </span>
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Deteksi anomali server real-time (Kritis: {{ $alertsSummary['critical'] ?? 0 }}, Peringatan: {{ $alertsSummary['warning'] ?? 0 }})
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('platform.alerts.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
                    >
                        <span>Lihat Semua Alert</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            @if(empty($topAlerts))
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-900 dark:text-white">
                            Tidak ada alert operasional aktif
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Semua langganan, pembayaran, kuota perangkat, dan cadangan database dalam kondisi normal.
                        </p>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    @foreach($topAlerts as $alert)
                        @php
                            $severity = (string) $alert['severity'];
                            $isCritical = $severity === \App\Support\PlatformOperationalAlertType::SEVERITY_CRITICAL;
                            $cardBorder = $isCritical
                                ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50/20 dark:bg-rose-950/10'
                                : 'border-amber-200 dark:border-amber-900/60 bg-amber-50/20 dark:bg-amber-950/10';
                            $badgeClasses = \App\Support\PlatformOperationalAlertType::severityBadgeClasses($severity);
                            $badgeLabel = \App\Support\PlatformOperationalAlertType::severityLabel($severity);
                        @endphp
                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border {{ $cardBorder }} shadow-xs flex flex-col justify-between space-y-3">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $badgeClasses }}">
                                        @if($isCritical)
                                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                                        @else
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                        @endif
                                        <span>{{ $badgeLabel }}</span>
                                    </span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium truncate max-w-[120px]">
                                        {{ $alert['business_name'] }}
                                    </span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white leading-snug">
                                    {{ $alert['title'] }}
                                </h4>
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed">
                                    {{ $alert['description'] }}
                                </p>
                            </div>
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <span class="text-[10px] text-slate-400 dark:text-slate-500">
                                    {{ $alert['occurred_at'] ? \Illuminate\Support\Carbon::parse($alert['occurred_at'])->diffForHumans() : 'Kondisi saat ini' }}
                                </span>
                                <a
                                    href="{{ $alert['action_url'] }}"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 inline-flex items-center gap-1"
                                >
                                    <span>Detail</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Section 2: Ringkasan Platform & Operasional --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            {{-- Panel 1: Subscription Breakdown --}}
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                <i data-lucide="badge-check" class="w-4 h-4"></i>
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Status Langganan
                            </h3>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            Paket & Status
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Free Tier</span>
                            </div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">
                                {{ number_format($subscriptions['plans']['free']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Cloud / Premium Tier</span>
                            </div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">
                                {{ number_format($subscriptions['plans']['cloud']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Kadaluarsa / Expired</span>
                            </div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white" data-testid="platform-sub-expired">
                                {{ number_format($subscriptions['statuses']['expired']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Nonaktif</span>
                            </div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white" data-testid="platform-sub-inactive">
                                {{ number_format($subscriptions['statuses']['inactive']) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                    <span>Total tercatat</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($subscriptions['total']) }} langganan</span>
                </div>
            </div>

            {{-- Panel 2: Sync Summary --}}
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Aktivitas Sinkronisasi
                            </h3>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            Sync Push
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Total Request Diproses</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white" data-testid="platform-total-sync-requests">
                                {{ number_format($sync['total_requests']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Perangkat Pernah Push</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white" data-testid="platform-synced-devices">
                                {{ number_format($sync['synced_devices']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Request 30 Hari Terakhir</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white" data-testid="platform-recent-sync-requests">
                                {{ number_format($sync['recent_30d']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Push Terakhir</span>
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                @if($sync['last_processed_at'])
                                    {{ \Illuminate\Support\Carbon::parse($sync['last_processed_at'])->diffForHumans() }}
                                @else
                                    <span class="text-slate-400 italic">Belum ada</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400">
                    <span>Mencatat event push database sync request</span>
                </div>
            </div>

            {{-- Panel 3: Billing & Revenue Monitoring --}}
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                                <i data-lucide="receipt" class="w-4 h-4"></i>
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Billing & Revenue
                            </h3>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60 uppercase">
                            Aktif
                        </span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/80 text-left space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Gateway Pembayaran:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Midtrans (Snap)</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Laporan Agregasi:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">Tersedia</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70 dark:border-slate-700/60 flex items-center justify-between gap-2">
                            <a
                                href="{{ route('platform.revenue.index') }}"
                                class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 inline-flex items-center gap-1"
                            >
                                <span>Laporan Revenue</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                            <a
                                href="{{ route('platform.payments.index') }}"
                                class="text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 inline-flex items-center gap-1"
                            >
                                <span>Riwayat Pembayaran</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5 shrink-0 text-emerald-500"></i>
                    <span>Tersinkronisasi dengan status canonical subscription payments</span>
                </div>
            </div>

        </div>

        {{-- Section 3: Recent Activity (Bisnis & Pengguna Terbaru) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- Latest Businesses --}}
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs" data-testid="platform-latest-businesses">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                            <i data-lucide="store" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Bisnis Terdaftar Terbaru
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                5 merchant terakhir yang didaftarkan
                            </p>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentActivity['businesses'] as $business)
                        <div class="py-3 flex items-center justify-between gap-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ $business->name }}
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono truncate">
                                    {{ $business->slug }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $business->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                    {{ $business->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $business->created_at ? $business->created_at->diffForHumans() : '-' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400 dark:text-slate-500 italic">
                            Belum ada bisnis terdaftar di database.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Latest Users --}}
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs" data-testid="platform-latest-users">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Pengguna Terdaftar Terbaru
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                5 akun pengguna terakhir di platform
                            </p>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentActivity['users'] as $u)
                        <div class="py-3 flex items-center justify-between gap-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ $u->name }}
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                    {{ $u->email }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold {{ $u->is_platform_admin ? 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-400 border border-purple-200/60 dark:border-purple-800/60' : ($u->businesses_exists ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60' : 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60') }}">
                                    {{ $u->is_platform_admin ? 'Platform Admin' : ($u->businesses_exists ? 'User Bisnis' : 'Belum Terhubung') }}
                                </span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $u->created_at ? $u->created_at->diffForHumans() : '-' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400 dark:text-slate-500 italic">
                            Belum ada pengguna terdaftar di database.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Section 4: Operator & Isolation Identity (Preserved for ADMIN-01 Contract & Security Verification) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Operator Identity Card --}}
            <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Operator Aktif</p>
                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate mt-0.5">{{ $user->name }}</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $user->email }}</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 shrink-0">
                    Terautentikasi
                </span>
            </div>

            {{-- Tenant Decoupling Card --}}
            <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Konteks Bisnis</p>
                    <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">Decoupled</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Independen dari business_user</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-400 shrink-0">
                    Zero Tenant
                </span>
            </div>

            {{-- Server Security Guard Card --}}
            <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Proteksi Otorisasi</p>
                    <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">EnsurePlatformAdmin</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Default Deny level server</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-400 shrink-0">
                    Guard Aktif
                </span>
            </div>

        </div>

    </div>
</x-layouts::platform>
