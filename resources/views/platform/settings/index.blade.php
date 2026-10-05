<x-layouts::platform :title="'Pengaturan Platform'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Pengaturan Platform
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Konfigurasi parameter operasional global runtime dan kill-switch kapabilitas SaaS Cloud.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-semibold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                <span>Fail-Closed &amp; Append-Only Audit</span>
            </div>
        </div>

        {{-- Flash Notification --}}
        @if (session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5"></i>
                <div class="text-xs font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5"></i>
                <div class="text-xs space-y-1">
                    <div class="font-bold">Terjadi kesalahan pada data yang dikirimkan:</div>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Context & Boundaries Info Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Pricing Governance Card --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="tags" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Harga Langganan Cloud (ADMIN-06)</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Dikelola kanonikal melalui model database paket langganan</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    Harga paket bulanan dan tahunan Cloud tidak dikonfigurasi melalui Platform Settings untuk mencegah duplikasi harga runtime. Perubahan nominal tarif dapat dilakukan di menu manajemen paket.
                </p>
                <div class="pt-1">
                    <a
                        href="{{ route('platform.subscription-plans.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors"
                    >
                        <span>Buka Manajemen Paket &amp; Harga</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            {{-- Secrets & Deployment Security Card --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Kredensial &amp; Secrets Deployment</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Keamanan level infrastruktur lingkungan server</p>
                    </div>
                </div>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-500 dark:text-slate-400">Midtrans Server Key</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">Environment (<code>.env</code>)</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-500 dark:text-slate-400">Midtrans Gateway Status</span>
                        @if ($isMidtransConfigured)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold border border-emerald-200/80 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Terkonfigurasi
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 text-[11px] font-bold border border-rose-200/80 dark:border-rose-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Belum Terkonfigurasi
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-slate-500 dark:text-slate-400">Mode Production Midtrans</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">Deployment Config</span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                    Kunci rahasia API, token snap, dan password database tidak pernah disimpan ke database atau dirender di browser.
                </p>
            </div>
        </div>

        {{-- Group 1: Cloud & Device Capacity --}}
        @php
            $deviceGroup = $groupedSettings[\App\Support\PlatformSettingDefinition::GROUP_CLOUD_DEVICES] ?? null;
        @endphp
        @if ($deviceGroup && !empty($deviceGroup['items']))
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <i data-lucide="smartphone" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $deviceGroup['label'] }}</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Batas kuota perangkat aktif untuk seluruh merchant dengan paket Cloud aktif.
                    </p>
                </div>

                <div class="p-5 space-y-4">
                    @foreach ($deviceGroup['items'] as $item)
                        <form action="{{ route('platform.settings.update', $item['slug']) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1 max-w-xl">
                                    <label for="input-{{ $item['slug'] }}" class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $item['label'] }}
                                    </label>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $item['description'] }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        <span>Batas izin: <strong>{{ $item['min'] }} - {{ $item['max'] }}</strong> perangkat</span>
                                        <span>&bull;</span>
                                        <span>Fallback bawaan: <strong>{{ $item['default'] }}</strong></span>
                                        @if ($item['is_persisted'])
                                            <span>&bull;</span>
                                            <span class="text-indigo-600 dark:text-indigo-400 font-medium">Nilai tersimpan di database</span>
                                            @if ($item['updated_at'])
                                                <span>(diperbarui {{ $item['updated_at'] }}{{ $item['updated_by'] ? ' oleh ' . $item['updated_by'] : '' }})</span>
                                            @endif
                                        @else
                                            <span>&bull;</span>
                                            <span class="text-slate-400 dark:text-slate-500 italic">Menggunakan nilai fallback</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="w-32">
                                        <input
                                            type="number"
                                            id="input-{{ $item['slug'] }}"
                                            name="value"
                                            value="{{ old('value', $item['effective_value']) }}"
                                            min="{{ $item['min'] }}"
                                            max="{{ $item['max'] }}"
                                            required
                                            class="w-full px-3 py-2 rounded-xl text-sm font-semibold text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400"
                                        >
                                    </div>
                                    <button
                                        type="submit"
                                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors shadow-xs"
                                    >
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/50 flex items-start gap-2.5">
                                <i data-lucide="info" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
                                <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
                                    <strong>Kebijakan Non-Destruktif:</strong> Jika batas diturunkan (misal dari 5 ke 3) sementara bisnis telah memiliki 5 perangkat aktif, sistem <em>tidak akan</em> menonaktifkan perangkat yang sudah ada. Namun, pendaftaran atau aktivasi perangkat baru untuk bisnis tersebut akan ditolak.
                                </p>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Group 2: Operational Alerts Thresholds --}}
        @php
            $alertsGroup = $groupedSettings[\App\Support\PlatformSettingDefinition::GROUP_ALERTS] ?? null;
        @endphp
        @if ($alertsGroup && !empty($alertsGroup['items']))
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <i data-lucide="bell" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $alertsGroup['label'] }}</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Ambang batas hari untuk menghitung alert operasional secara real-time pada dashboard platform.
                    </p>
                </div>

                <div class="p-5 space-y-6 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($alertsGroup['items'] as $item)
                        <form action="{{ route('platform.settings.update', $item['slug']) }}" method="POST" class="pt-6 first:pt-0 space-y-3">
                            @csrf
                            @method('PATCH')

                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1 max-w-xl">
                                    <label for="input-{{ $item['slug'] }}" class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $item['label'] }}
                                    </label>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $item['description'] }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        <span>Rentang: <strong>{{ $item['min'] }} - {{ $item['max'] }}</strong> hari</span>
                                        <span>&bull;</span>
                                        <span>Fallback: <strong>{{ $item['default'] }} hari</strong></span>
                                        @if ($item['is_persisted'])
                                            <span>&bull;</span>
                                            <span class="text-indigo-600 dark:text-indigo-400 font-medium">Database (diperbarui {{ $item['updated_at'] }}{{ $item['updated_by'] ? ' oleh ' . $item['updated_by'] : '' }})</span>
                                        @else
                                            <span>&bull;</span>
                                            <span class="text-slate-400 dark:text-slate-500 italic">Menggunakan nilai fallback</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="w-32 relative">
                                        <input
                                            type="number"
                                            id="input-{{ $item['slug'] }}"
                                            name="value"
                                            value="{{ old('value', $item['effective_value']) }}"
                                            min="{{ $item['min'] }}"
                                            max="{{ $item['max'] }}"
                                            required
                                            class="w-full pr-10 pl-3 py-2 rounded-xl text-sm font-semibold text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400"
                                        >
                                        <span class="absolute right-3 top-2.5 text-xs text-slate-400 font-medium">hari</span>
                                    </div>
                                    <button
                                        type="submit"
                                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors shadow-xs"
                                    >
                                        Perbarui Ambang
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Group 3: Feature Kill-Switches --}}
        @php
            $featuresGroup = $groupedSettings[\App\Support\PlatformSettingDefinition::GROUP_FEATURES] ?? null;
        @endphp
        @if ($featuresGroup && !empty($featuresGroup['items']))
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <i data-lucide="power" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $featuresGroup['label'] }}</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Emergency shutdown switch untuk menonaktifkan kapabilitas Cloud secara instan pada level runtime aplikasi.
                    </p>
                </div>

                {{-- Impact Warning --}}
                <div class="p-4 mx-5 my-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-800/50 flex items-start gap-3">
                    <i data-lucide="alert-octagon" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5"></i>
                    <div class="text-xs text-rose-800 dark:text-rose-200 space-y-1">
                        <p class="font-bold">Peringatan Dampak Kill-Switch:</p>
                        <p class="leading-relaxed">
                            Menonaktifkan fitur akan segera menolak akses Cloud terkait untuk seluruh bisnis tanpa menghapus data yang tersimpan. Ketersediaan implementasi backend (backend readiness config) adalah <strong>hard ceiling</strong> mutlak: jika backend belum siap (false), fitur tidak dapat diaktifkan melalui runtime.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-5 py-3">Kapabilitas Cloud</th>
                                <th class="px-4 py-3 text-center">Backend Ready</th>
                                <th class="px-4 py-3 text-center">Runtime Switch</th>
                                <th class="px-4 py-3 text-center">Status Efektif</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            @foreach ($featuresGroup['items'] as $item)
                                @php
                                    $backendReady = $item['backend_ready'] ?? false;
                                    $runtimeEnabled = $item['runtime_enabled'] ?? false;
                                    $effectiveStatus = $item['effective_status'] ?? false;
                                @endphp
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-5 py-4 space-y-0.5">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $item['label'] }}</div>
                                        <div class="text-slate-500 dark:text-slate-400 text-[11px] leading-relaxed max-w-md">
                                            {{ $item['description'] }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">
                                            Key: <code>{{ $item['key'] }}</code>
                                        </div>
                                    </td>

                                    {{-- Backend Ready --}}
                                    <td class="px-4 py-4 text-center">
                                        @if ($backendReady)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-[11px] font-bold">
                                                <i data-lucide="check" class="w-3 h-3"></i>
                                                Tersedia
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 text-[11px] font-semibold" title="Config backend implementation availability = false">
                                                <i data-lucide="x" class="w-3 h-3"></i>
                                                Tidak Tersedia
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Runtime Switch --}}
                                    <td class="px-4 py-4 text-center">
                                        @if ($runtimeEnabled)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-[11px] font-bold">
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-[11px] font-bold">
                                                Nonaktif
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Status Efektif --}}
                                    <td class="px-4 py-4 text-center">
                                        @if ($effectiveStatus)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-200 font-black text-[11px]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Dapat Digunakan
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-200 font-black text-[11px]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                Ditolak
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Action Form --}}
                                    <td class="px-5 py-4 text-right">
                                        @if (! $backendReady)
                                            <button
                                                type="button"
                                                disabled
                                                title="Fitur belum tersedia di backend (Hard Ceiling)"
                                                class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-600 text-xs font-semibold cursor-not-allowed"
                                            >
                                                Terkunci Backend
                                            </button>
                                        @else
                                            <form action="{{ route('platform.settings.update', $item['slug']) }}" method="POST" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="value" value="{{ $runtimeEnabled ? '0' : '1' }}">

                                                @if ($runtimeEnabled)
                                                    <button
                                                        type="submit"
                                                        class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold transition-colors"
                                                        onclick="return confirm('Apakah Anda yakin ingin menonaktifkan fitur {{ $item['label'] }}? Akses fitur ini akan segera ditolak untuk semua bisnis.')"
                                                    >
                                                        Matikan (Kill-Switch)
                                                    </button>
                                                @else
                                                    <button
                                                        type="submit"
                                                        class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold transition-colors"
                                                    >
                                                        Aktifkan Kembali
                                                    </button>
                                                @endif
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-layouts::platform>
