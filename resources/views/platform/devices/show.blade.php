<x-layouts::platform :title="'Detail Perangkat ' . $device->name">
    <div class="space-y-6">

        {{-- Breadcrumb & Back Action --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('platform.devices.index') }}"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors"
                    title="Kembali ke Daftar Perangkat"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ $device->name }}
                        </h1>
                        @if($device->status === \App\Models\Device::STATUS_ACTIVE)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                Nonaktif
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Identifier: <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $device->identifier }}</span> &bull; ID #{{ $device->id }}
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

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left 2 Columns: Device, Business & Outlet Details --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Device Information Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i data-lucide="smartphone" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        <span>Informasi Perangkat</span>
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Nama Perangkat</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $device->name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Identifier Perangkat</span>
                            <span class="font-mono font-medium text-slate-800 dark:text-slate-200 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 inline-block">
                                {{ $device->identifier }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Platform OS</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200 uppercase">{{ $device->platform ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Status Perangkat</span>
                            @if($device->status === \App\Models\Device::STATUS_ACTIVE)
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">Aktif</span>
                            @else
                                <span class="text-slate-500 dark:text-slate-400 font-bold">Nonaktif (Dicabut)</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Terdaftar Pada</span>
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $device->registered_at ? $device->registered_at->timezone('Asia/Jakarta')->format('d F Y H:i:s') : '-' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Aktivitas Terakhir</span>
                            @if($device->last_seen_at)
                                <span class="text-slate-700 dark:text-slate-300 font-medium">
                                    {{ $device->last_seen_at->timezone('Asia/Jakarta')->format('d F Y H:i:s') }}
                                    <span class="text-slate-400 font-normal">({{ $device->last_seen_at->diffForHumans() }})</span>
                                </span>
                            @else
                                <span class="text-slate-400 italic">Belum pernah aktif atau melakukan sinkronisasi</span>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Catatan Perangkat</span>
                            <p class="text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                {{ $device->notes ?: 'Tidak ada catatan tambahan.' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Dibuat</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $device->created_at ? $device->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block mb-0.5">Terakhir Diperbarui</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $device->updated_at ? $device->updated_at->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Business & Outlet Context Card --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Business Card --}}
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="building-2" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            <span>Bisnis Terkait</span>
                        </h3>
                        @if($business)
                            <div class="text-xs space-y-2">
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Nama Bisnis</span>
                                    <a
                                        href="{{ route('platform.businesses.show', $business) }}"
                                        class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                                    >
                                        {{ $business->name }}
                                    </a>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Status Bisnis</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 capitalize">{{ $business->status }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Paket Langganan</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 uppercase">
                                        {{ $business->subscription ? $business->subscription->plan : 'Free' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Akses Cloud Entitlement</span>
                                    @if($hasCloudAccess)
                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-bold text-[11px]">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                            Tidak Ada Akses (Free / Expired)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">Bisnis tidak ditemukan atau telah dihapus.</p>
                        @endif
                    </div>

                    {{-- Outlet Card --}}
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="store" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            <span>Outlet Penempatan</span>
                        </h3>
                        @if($device->outlet)
                            <div class="text-xs space-y-2">
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Nama Outlet</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ $device->outlet->name }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Outlet ID</span>
                                    <span class="font-mono text-slate-600 dark:text-slate-400">#{{ $device->outlet->id }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Imutabilitas Penempatan</span>
                                    <span class="text-slate-500 text-[11px]">Penempatan outlet terikat permanen dengan histori sinkronisasi perangkat.</span>
                                </div>
                            </div>
                        @else
                            <div class="text-xs text-slate-400 italic p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                                Tidak terhubung ke outlet.
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Right Column: Quota Context & Lifecycle Action --}}
            <div class="space-y-6">

                {{-- Cloud Quota Context Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="pie-chart" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        <span>Konsumsi Kuota Cloud</span>
                    </h2>

                    @if(! $hasCloudAccess)
                        <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                                <span>Akses Cloud Tidak Aktif</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Bisnis ini tidak memiliki entitlement Cloud aktif (Free Tier atau paket Cloud telah kedaluwarsa). Kuota perangkat tidak dapat digunakan sampai langganan diaktifkan.
                            </p>
                        </div>
                    @endif

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Batas Kuota Cloud</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $limit > 0 ? $limit . ' Perangkat' : 'Tidak Terbatas' }}</span>
                        </div>

                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Perangkat Aktif Bisnis</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $activeCount }} / {{ $limit }}</span>
                        </div>

                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Sisa Slot Tersedia</span>
                            <span class="font-bold {{ $isReached ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $remaining !== null ? $remaining . ' Slot' : 'Tidak Terbatas' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Dihitung ke Kuota</span>
                            @if($isCounted)
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Ya (Mengonsumsi 1 Slot)</span>
                            @else
                                <span class="font-bold text-slate-500 dark:text-slate-400">Tidak (Nonaktif)</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Lifecycle Operations Card --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        <span>Aksi Siklus Hidup Perangkat</span>
                    </h2>

                    @if($device->status === \App\Models\Device::STATUS_ACTIVE)
                        {{-- Deactivate Action --}}
                        <div class="space-y-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Menonaktifkan perangkat akan mencabut akses sinkronisasi Cloud perangkat ini dan membebaskan 1 slot kuota Cloud bisnis.
                            </p>
                            <form
                                method="POST"
                                action="{{ route('platform.devices.deactivate', $device) }}"
                                onsubmit="return confirm('Perangkat akan dinonaktifkan dari akses Cloud.\n\nData perangkat dan histori penjualan tidak akan dihapus.\n\nLanjutkan penonaktifan?');"
                            >
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-bold transition-colors shadow-xs"
                                >
                                    <i data-lucide="slash" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
                                    <span>Nonaktifkan / Cabut Perangkat</span>
                                </button>
                            </form>
                        </div>
                    @else
                        {{-- Reactivate Action --}}
                        <div class="space-y-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Mengaktifkan kembali perangkat memerlukan bisnis memiliki akses Cloud aktif serta tersedianya slot kuota.
                            </p>

                            @if(! $hasCloudAccess)
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-xs text-slate-500">
                                    Reaktivasi tidak dapat dilakukan karena bisnis tidak memiliki langganan Cloud aktif.
                                </div>
                            @elseif($isReached)
                                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-400">
                                    Reaktivasi tidak dapat dilakukan karena batas kuota Cloud ({{ $limit }} perangkat) telah penuh. Nonaktifkan perangkat lain terlebih dahulu.
                                </div>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('platform.devices.activate', $device) }}"
                                    onsubmit="return confirm('Perangkat akan diaktifkan kembali dan mengonsumsi 1 slot kuota Cloud bisnis.\n\nLanjutkan aktivasi?');"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-xs"
                                    >
                                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                                        <span>Aktifkan Kembali Perangkat</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 leading-relaxed">
                        <strong>Catatan Integritas:</strong> Menghapus catatan perangkat fisik tidak disediakan agar rekaman audit transaksi dan sinkronisasi tetap utuh.
                    </div>
                </div>

            </div>

        </div>

    </div>
</x-layouts::platform>
