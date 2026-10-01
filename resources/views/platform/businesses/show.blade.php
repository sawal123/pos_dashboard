<x-layouts::platform :title="'Detail Bisnis: ' . $business->name">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Back Navigation & Page Header --}}
        <div class="flex flex-col gap-3">
            <div>
                <a
                    href="{{ route('platform.businesses.index') }}"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Kembali ke Daftar Bisnis</span>
                </a>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $business->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $business->status === 'active' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            {{ $business->status === 'active' ? 'Aktif' : 'Nonaktif (Suspended)' }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                            {{ $business->businessTypeLabel() }}
                        </span>
                        <span class="font-mono text-xs text-slate-400 dark:text-slate-500">
                            #{{ $business->id }} &bull; {{ $business->slug }}
                        </span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ $business->name }}
                    </h1>
                </div>

                {{-- Status Mutation Action (Suspend / Reactivate) --}}
                <div class="shrink-0 flex items-center gap-2">
                    @if($business->status === 'active')
                        <form
                            method="POST"
                            action="{{ route('platform.businesses.status.update', $business) }}"
                            onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan (suspend) bisnis \'{{ addslashes($business->name) }}\'? Perubahan status merupakan aksi administratif level platform.');"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="inactive" />
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/50 border border-rose-200/80 dark:border-rose-800/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors shadow-xs focus:outline-none focus:ring-2 focus:ring-rose-500"
                            >
                                <i data-lucide="ban" class="w-4 h-4 text-rose-500"></i>
                                <span>Suspend Bisnis</span>
                            </button>
                        </form>
                    @else
                        <form
                            method="POST"
                            action="{{ route('platform.businesses.status.update', $business) }}"
                            onsubmit="return confirm('Apakah Anda yakin ingin mengaktifkan kembali bisnis \'{{ addslashes($business->name) }}\'?');"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active" />
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/80 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition-colors shadow-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                                <span>Aktifkan Bisnis</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section 1: KPI Quick Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Outlets --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Outlet</span>
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($business->outlets_count) }}
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Titik outlet terdaftar</p>
            </div>

            {{-- Users --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Pengguna</span>
                    <span class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <i data-lucide="users" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($business->users_count) }}
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Total anggota bisnis</p>
            </div>

            {{-- Devices --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Perangkat POS</span>
                    <span class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <i data-lucide="tablet" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($business->devices_count) }}
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                    <span>Aktif: <strong class="text-emerald-600 dark:text-emerald-400">{{ $business->active_devices_count }}</strong></span>
                    <span>&bull;</span>
                    <span>Nonaktif: <strong class="text-slate-700 dark:text-slate-300">{{ $business->inactive_devices_count }}</strong></span>
                </div>
            </div>

            {{-- Subscription --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Langganan</span>
                    <span class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    @if($business->subscription)
                        {{ ucfirst($business->subscription->plan) }}
                    @else
                        Free
                    @endif
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    Status: <strong class="font-semibold text-slate-700 dark:text-slate-200">{{ $business->subscription ? ucfirst($business->subscription->status) : 'Aktif' }}</strong>
                </p>
            </div>
        </div>

        {{-- Section 2: Detailed Information Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Left Column: General & Membership --}}
            <div class="space-y-6">

                {{-- Business Metadata Card --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                            <i data-lucide="info" class="w-4 h-4"></i>
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Identitas & Metadata Bisnis
                        </h2>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">ID Bisnis</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white">#{{ $business->id }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Nama Bisnis</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $business->name }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Slug</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ $business->slug }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Tipe Bisnis</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $business->businessTypeLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Status Administratif</span>
                            <span class="font-bold {{ $business->status === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $business->status === 'active' ? 'Aktif' : 'Nonaktif (Suspended)' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Terdaftar Pada</span>
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $business->created_at ? $business->created_at->format('d F Y, H:i:s') : '-' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-slate-500 dark:text-slate-400">Terakhir Diperbarui</span>
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $business->updated_at ? $business->updated_at->format('d F Y, H:i:s') : '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Owner & Membership Summary Card --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                                <i data-lucide="crown" class="w-4 h-4"></i>
                            </span>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                Owner & Keanggotaan
                            </h2>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            {{ $business->users_count }} Anggota Terhubung
                        </span>
                    </div>

                    <div class="space-y-3">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            Pemilik Terdaftar (Owners)
                        </p>
                        @forelse($business->owners as $owner)
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                        {{ $owner->name }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {{ $owner->email }}
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60 shrink-0">
                                    Pemilik
                                </span>
                            </div>
                        @empty
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 text-center text-xs text-slate-400 dark:text-slate-500 italic">
                                Belum ada akun pemilik yang terhubung ke bisnis ini.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- Right Column: Subscription, Devices, Outlets --}}
            <div class="space-y-6">

                {{-- Subscription Details Card --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                            <i data-lucide="badge-check" class="w-4 h-4"></i>
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Status Langganan
                        </h2>
                    </div>

                    @if($business->subscription)
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Paket Langganan</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ ucfirst($business->subscription->plan) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Status</span>
                                <span class="font-bold {{ $business->subscription->status === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ ucfirst($business->subscription->status) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Mulai Aktif</span>
                                <span class="text-slate-700 dark:text-slate-300">
                                    {{ $business->subscription->starts_at ? $business->subscription->starts_at->format('d M Y, H:i') : '-' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-slate-500 dark:text-slate-400">Berlaku Sampai</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                    {{ $business->subscription->expires_at ? $business->subscription->expires_at->format('d M Y, H:i') : 'Tidak Terbatas' }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 text-center text-xs text-slate-500 dark:text-slate-400">
                            Bisnis ini beroperasi dengan tier <strong>Free</strong> default.
                        </div>
                    @endif
                </div>

                {{-- Devices & Sync Summary Card --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Perangkat & Aktivitas Sinkronisasi
                        </h2>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Total Perangkat Terdaftar</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($business->devices_count) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Perangkat Aktif / Nonaktif</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ $business->active_devices_count }} Aktif / {{ $business->inactive_devices_count }} Nonaktif
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Total Request Push Sync</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($business->sync_requests_count) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-slate-500 dark:text-slate-400">Sinkronisasi Terakhir</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                @if($lastSync)
                                    {{ \Illuminate\Support\Carbon::parse($lastSync)->diffForHumans() }}
                                @else
                                    <span class="text-slate-400 italic">Belum ada</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Outlets Summary Card --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold">
                                <i data-lucide="store" class="w-4 h-4"></i>
                            </span>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                Daftar Outlet (Maks. 10)
                            </h2>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            {{ $business->outlets_count }} Outlet Total
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($business->outlets as $outlet)
                            <div class="py-2.5 flex items-center justify-between text-xs first:pt-0 last:pb-0">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $outlet->name }}</span>
                                <span class="text-slate-400 font-mono text-[11px]">{{ $outlet->created_at ? $outlet->created_at->format('d M Y') : '-' }}</span>
                            </div>
                        @empty
                            <div class="py-4 text-center text-xs text-slate-400 dark:text-slate-500 italic">
                                Belum ada outlet terdaftar untuk bisnis ini.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>

    </div>
</x-layouts::platform>
