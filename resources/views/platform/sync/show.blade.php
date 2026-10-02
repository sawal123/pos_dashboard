<x-layouts::platform :title="'Detail Sync Request ' . $syncRequest->request_id">
    <div class="space-y-6">

        {{-- Breadcrumb & Back Action --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('platform.sync.index') }}"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors"
                    title="Kembali ke Daftar Sinkronisasi"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            Detail Sync Request
                        </h1>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-indigo-500"></i>
                            Committed
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-mono">
                        {{ $syncRequest->request_id }} &bull; ID #{{ $syncRequest->id }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($business)
                    <a
                        href="{{ route('platform.businesses.show', $business) }}"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors"
                    >
                        <i data-lucide="building-2" class="w-4 h-4 text-slate-400"></i>
                        <span>Lihat Bisnis</span>
                    </a>
                @endif
                @if($device)
                    <a
                        href="{{ route('platform.devices.show', $device) }}"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors"
                    >
                        <i data-lucide="smartphone" class="w-4 h-4 text-slate-400"></i>
                        <span>Lihat Perangkat</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left 2 Columns: Request, Business & Device Details --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Request Information Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Informasi Request
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Database ID</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white">#{{ $syncRequest->id }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Status Server</span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                                <i data-lucide="check" class="w-3 h-3 text-indigo-500"></i>
                                Committed
                            </span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Request UUID</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white break-all bg-slate-50 dark:bg-slate-800/60 px-3 py-1.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60 block">
                                {{ $syncRequest->request_id }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Waktu Diproses (processed_at)</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $syncRequest->processed_at->format('d M Y · H:i:s') }}</span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 block font-mono">{{ $syncRequest->processed_at->toIso8601String() }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Waktu Dibuat (created_at)</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ $syncRequest->created_at?->format('d M Y · H:i:s') ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Business Information Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="building-2" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            Bisnis Pengirim
                        </h2>
                        @if($business)
                            <a
                                href="{{ route('platform.businesses.show', $business) }}"
                                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                            >
                                <span>Lihat Profil Bisnis</span>
                                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>
                    @if($business)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Nama Bisnis</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $business->name }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Slug</span>
                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $business->slug }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Status Akun Bisnis</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $business->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ ucfirst($business->status) }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Akses Cloud Saat Ini</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $hasCloudAccess ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400' }}">
                                    {{ $hasCloudAccess ? 'Granted (Aktif)' : 'Denied (Nonaktif / Expired)' }}
                                </span>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 dark:text-slate-500 italic">
                            Data bisnis tidak ditemukan atau telah dihapus.
                        </p>
                    @endif
                </div>

                {{-- Device & Outlet Information Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="smartphone" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            Perangkat &amp; Outlet
                        </h2>
                        @if($device)
                            <a
                                href="{{ route('platform.devices.show', $device) }}"
                                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                            >
                                <span>Lihat Perangkat</span>
                                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>
                    @if($device)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Nama Perangkat</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $device->name }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Identifier Perangkat</span>
                                <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $device->identifier }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Status Perangkat Saat Ini</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $device->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ ucfirst($device->status) }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Platform</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ $device->platform ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Terakhir Terlihat (Device)</span>
                                <span class="font-medium text-slate-900 dark:text-white">
                                    {{ $device->last_seen_at ? $device->last_seen_at->format('d M Y · H:i:s') : 'Belum Ada' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Diagnostik Aktivitas Device</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $activity['badge_class'] }}">
                                    {{ $activity['label'] }}
                                </span>
                            </div>
                            <div class="sm:col-span-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400 block mb-1">Outlet Terkait</span>
                                <span class="font-bold text-slate-900 dark:text-white">
                                    {{ $outlet?->name ?? 'Tidak Terhubung ke Outlet' }}
                                </span>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 dark:text-slate-500 italic">
                            Data perangkat tidak terhubung, data mismatch, atau telah dihapus.
                        </p>
                    @endif
                </div>

            </div>

            {{-- Right 1 Column: Sync Context & Observability Limitations --}}
            <div class="space-y-6">

                {{-- Server Sync Context Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Konteks Server
                    </h2>
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Business Server Sequence</span>
                            <div class="text-lg font-black text-slate-900 dark:text-white">
                                {{ number_format($serverSequence) }}
                            </div>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-0.5">
                                Sequence dihitung per-bisnis secara independen (SyncCounter).
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Status Persistensi</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                YA (Tersimpan pada tabel sync_requests)
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Idempotensi Server</span>
                            <span class="text-slate-700 dark:text-slate-300">
                                Kunci unik komposit: <code class="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">business_id + device_id + request_id</code>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Historical vs Current Separation Card --}}
                <div class="p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs space-y-2">
                    <div class="flex items-center gap-2 text-slate-900 dark:text-white font-bold">
                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                        <span>Pemisahan Riwayat &amp; Status Saat Ini</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 text-[11px] leading-relaxed">
                        Request sinkronisasi ini telah berhasil committed di masa lalu. Apabila saat ini status perangkat berubah menjadi <strong class="text-slate-800 dark:text-slate-200">nonaktif</strong> atau langganan bisnis saat ini <strong class="text-slate-800 dark:text-slate-200">kedaluwarsa</strong>, hal tersebut merupakan status operasional saat ini dan <strong>tidak membatalkan keabsahan historis request ini</strong>.
                    </p>
                </div>

                {{-- Observability Limitations Card --}}
                <div class="p-5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/30 text-xs space-y-3">
                    <div class="flex items-center gap-2 text-amber-900 dark:text-amber-300 font-bold">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span>Keterbatasan Observabilitas</span>
                    </div>
                    <ul class="space-y-2 text-[11px] text-amber-800/90 dark:text-amber-300/90 leading-relaxed list-disc list-inside">
                        <li>
                            <strong>Payload request tidak disimpan</strong> pada tabel <code class="font-mono text-[10px]">sync_requests</code> demi keamanan dan kapasitas database.
                        </li>
                        <li>
                            Server hanya mencatat sync push yang berhasil committed. Upaya sync yang gagal sebelum commit tidak memiliki record.
                        </li>
                        <li>
                            Pending queue lokal dikelola oleh POS Mobile masing-masing dan tidak tersedia di server.
                        </li>
                        <li>
                            Histori konflik tidak disimpan dalam database canonical server.
                        </li>
                    </ul>
                </div>

            </div>

        </div>

    </div>
</x-layouts::platform>
