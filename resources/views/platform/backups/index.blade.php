<x-layouts::platform :title="'Backup Cloud'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Monitoring Backup Cloud
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Status kesiapan infrastruktur, kapabilitas produk, dan ketersediaan backend pencadangan data cloud.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200/80 dark:border-amber-800/80 shadow-xs text-xs font-bold text-amber-700 dark:text-amber-400 shrink-0">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Status Backend: <strong>{{ $readiness_summary['backend_status'] }}</strong></span>
            </div>
        </div>

        {{-- Readiness Status Cards (Honest Observability — 0 Fake Telemetry) --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            {{-- Backend Engine Status --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Backend Engine</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="server-off" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-sm md:text-base font-black text-amber-600 dark:text-amber-400">
                    {{ $readiness_summary['backend_status'] }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    API route tersedia; worker/queue belum ada
                </div>
            </div>

            {{-- Backup History Status --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Riwayat Backup</span>
                    <span class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                        <i data-lucide="archive" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-sm md:text-base font-black text-slate-700 dark:text-slate-300">
                    {{ $readiness_summary['backup_history'] }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Tabel tersedia; telemetri riwayat belum dibangun
                </div>
            </div>

            {{-- Restore History Status --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Riwayat Restore</span>
                    <span class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                        <i data-lucide="history" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-sm md:text-base font-black text-slate-700 dark:text-slate-300">
                    {{ $readiness_summary['restore_history'] }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Tidak ada tabel pelacakan pemulihan
                </div>
            </div>

            {{-- Storage Usage Status --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Penggunaan Storage</span>
                    <span class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                        <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-sm md:text-base font-black text-slate-700 dark:text-slate-300">
                    {{ $readiness_summary['storage_usage'] }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                    Belum ada integrasi object storage
                </div>
            </div>
        </div>

        {{-- Primary Informational Notice --}}
        <div class="flex items-start gap-3 p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200/70 dark:border-indigo-900/40 text-xs text-indigo-950 dark:text-indigo-200">
            <i data-lucide="info" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
            <div class="space-y-1.5">
                <span class="font-bold text-sm block">Kondisi Kesiapan Fitur (Observabilitas Jujur):</span>
                <p class="text-indigo-900/90 dark:text-indigo-300 text-xs leading-relaxed">
                    Kapabilitas <strong>Cloud Backup</strong> dan <strong>Cloud Restore</strong> adalah fitur produk resmi yang telah dideklarasikan dalam kebijakan produk (<code class="font-mono text-[11px] bg-indigo-100/80 dark:bg-indigo-900/50 px-1 py-0.5 rounded">config/premium.php</code> &amp; <code class="font-mono text-[11px] bg-indigo-100/80 dark:bg-indigo-900/50 px-1 py-0.5 rounded">PremiumPolicy</code>). Backend <strong>Cloud Backup</strong> (unggah, daftar, detail, unduh snapshot privat) <strong>sudah tersedia</strong> di server; eksekusi restore tetap di perangkat mobile (PREM-M06) dan telemetri operasional belum dibangun.
                </p>
                <p class="text-indigo-800/80 dark:text-indigo-300/80 text-[11px] leading-relaxed">
                    Halaman ini berfungsi sebagai observabilitas kesiapan (readiness monitoring) yang akurat. Tidak ada data fiktif atau metrik nol semu yang dimanipulasi. Seluruh aksi operasional pencadangan bersifat read-only.
                </p>
            </div>
        </div>

        {{-- Declared Product Capabilities --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Cloud Backup Capability --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                            <i data-lucide="cloud-upload" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ $capabilities['cloud_backup']['name'] }}
                            </h2>
                            <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                                {{ $capabilities['cloud_backup']['code'] }}
                            </span>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-400 border border-sky-200/60 dark:border-sky-800/60">
                        <i data-lucide="check" class="w-3 h-3 text-sky-500"></i>
                        Terdaftar (Declared)
                    </span>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 mb-3 leading-relaxed">
                    {{ $capabilities['cloud_backup']['description'] }}
                </p>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-slate-600 dark:text-slate-400">
                            Declared: <strong class="{{ $capabilities['cloud_backup']['declared'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">{{ $capabilities['cloud_backup']['declared'] ? 'YA' : 'TIDAK' }}</strong>
                        </span>
                        <span class="text-slate-600 dark:text-slate-400">
                            Available: <strong class="{{ $capabilities['cloud_backup']['backend_available'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $capabilities['cloud_backup']['backend_available'] ? 'YA' : 'TIDAK' }}</strong>
                        </span>
                    </div>
                    <span class="font-bold {{ $capabilities['cloud_backup']['backend_available'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $capabilities['cloud_backup']['status_label'] }}
                    </span>
                </div>
            </div>

            {{-- Cloud Restore Capability --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                            <i data-lucide="cloud-download" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ $capabilities['cloud_restore']['name'] }}
                            </h2>
                            <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                                {{ $capabilities['cloud_restore']['code'] }}
                            </span>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-400 border border-sky-200/60 dark:border-sky-800/60">
                        <i data-lucide="check" class="w-3 h-3 text-sky-500"></i>
                        Terdaftar (Declared)
                    </span>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 mb-3 leading-relaxed">
                    {{ $capabilities['cloud_restore']['description'] }}
                </p>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="text-slate-600 dark:text-slate-400">
                            Declared: <strong class="{{ $capabilities['cloud_restore']['declared'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">{{ $capabilities['cloud_restore']['declared'] ? 'YA' : 'TIDAK' }}</strong>
                        </span>
                        <span class="text-slate-600 dark:text-slate-400">
                            Available: <strong class="{{ $capabilities['cloud_restore']['backend_available'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $capabilities['cloud_restore']['backend_available'] ? 'YA' : 'TIDAK' }}</strong>
                        </span>
                    </div>
                    <span class="font-bold {{ $capabilities['cloud_restore']['backend_available'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $capabilities['cloud_restore']['status_label'] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Feature Availability Matrix Table --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="check-square" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Matriks Kesiapan Fitur Cloud Backup &amp; Restore
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Daftar komponen sistem dan status verifikasi implementasi di server.
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50/75 dark:bg-slate-800/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">Komponen Sistem</th>
                            <th scope="col" class="px-4 py-3">Kategori</th>
                            <th scope="col" class="px-4 py-3">Status Ketersediaan</th>
                            <th scope="col" class="px-4 py-3">Catatan Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800">
                        @foreach($readiness_matrix as $row)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                    {{ $row['component'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400 text-[11px]">
                                    {{ $row['type'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($row['is_ready'])
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <i data-lucide="check-circle" class="w-3 h-3 text-emerald-500"></i>
                                            {{ $row['status'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                                            <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-500"></i>
                                            {{ $row['status'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $row['notes'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Domain Boundaries & Disclaimers --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Domain Separation --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    Pemisahan Batasan Domain
                </h3>
                <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed list-disc list-inside">
                    <li>
                        <strong>Sync Bukan Backup:</strong> Tabel <code class="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">sync_requests</code> hanya mencatat idempotensi request transaksi offline-ke-online, bukan backup snapshot basis data.
                    </li>
                    <li>
                        <strong>Backup Lokal POS Mobile Bukan Cloud:</strong> Fitur backup lokal pada aplikasi kasir bekerja secara offline pada file sistem SQLite lokal perangkat dan tidak dikirim ke server.
                    </li>
                    <li>
                        <strong>Bebas Surrogate Metric:</strong> Waktu <code class="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">last_seen_at</code> atau <code class="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">processed_at</code> tidak pernah dijadikan penanda waktu backup.
                    </li>
                </ul>
            </div>

            {{-- Entitlement Context --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    Konteks Hak Akses (Product Entitlement)
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Langganan <strong>Cloud</strong> adalah prasyarat produk agar bisnis berhak menggunakan kapabilitas Cloud Backup dan Restore di masa depan.
                </p>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 text-[11px] text-slate-600 dark:text-slate-400">
                    <strong>Penting:</strong> Cloud Backup aktif untuk bisnis dengan langganan Cloud yang sah; eksekusi restore penuh dan telemetri operasional masih dalam pengembangan.
                </div>
            </div>
        </div>

        {{-- Observability Requirements When Backend is Built --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="layers" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    Kebutuhan Observabilitas Saat Backend Dibangun
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Ketika backend Cloud Backup dibangun, kebutuhan telemetri dan metadata konseptual berikut harus dipenuhi untuk monitoring operasional:
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach(($future_observability_requirements ?? $future_telemetry_contract) as $item)
                    <div class="p-3 rounded-xl bg-slate-50/75 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800 text-xs">
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-[11px]">
                                {{ $item['field'] }}
                            </span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">
                                {{ $item['type'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-normal">
                            {{ $item['description'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-layouts::platform>
