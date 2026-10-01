<x-layouts::platform :title="'Detail Langganan: ' . ($business ? $business->name : '#' . $subscription->id)">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @php
            $hasCloud = $subscription->hasCloudAccess();
            $isCloud = $subscription->isCloud();
            $expiresAt = $subscription->expires_at;
            $isExpired = $subscription->isExpired();
        @endphp

        {{-- Back Navigation & Page Header --}}
        <div class="flex flex-col gap-3">
            <div>
                <a
                    href="{{ route('platform.subscriptions.index') }}"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Kembali ke Daftar Langganan</span>
                </a>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br {{ $hasCloud ? 'from-indigo-600 to-purple-700 text-white' : 'from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-slate-700 dark:text-slate-200' }} flex items-center justify-center font-black text-lg shrink-0 shadow-xs">
                        <i data-lucide="{{ $hasCloud ? 'sparkles' : 'box' }}" class="w-7 h-7"></i>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $isCloud ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                <i data-lucide="{{ $isCloud ? 'cloud' : 'box' }}" class="w-3.5 h-3.5"></i>
                                Paket {{ ucfirst($subscription->plan) }}
                            </span>

                            @if($subscription->status === \App\Models\Subscription::STATUS_ACTIVE)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            @elseif($subscription->status === \App\Models\Subscription::STATUS_EXPIRED)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    Expired
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Nonaktif
                                </span>
                            @endif

                            @if($hasCloud)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <i data-lucide="shield-check" class="w-3 h-3"></i>
                                    Cloud Entitlement Granted
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    <i data-lucide="shield-x" class="w-3 h-3"></i>
                                    Cloud Entitlement Denied
                                </span>
                            @endif

                            <span class="font-mono text-xs text-slate-400 dark:text-slate-500">
                                Sub ID: #{{ $subscription->id }}
                            </span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ $business ? $business->name : 'Langganan #' . $subscription->id }}
                        </h1>
                    </div>
                </div>

                @if($business)
                    <div class="shrink-0">
                        <a
                            href="{{ route('platform.businesses.show', $business) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors shadow-xs"
                        >
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                            <span>Buka Profil Bisnis</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section 1: Detail Langganan & Masa Berlaku --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Informasi Langganan & Masa Berlaku</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Metadata paket, status operasional, dan periode langganan</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Paket Langganan
                    </span>
                    <span class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ ucfirst($subscription->plan) }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Status Langganan
                    </span>
                    <span class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ ucfirst($subscription->status) }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Tanggal Mulai
                    </span>
                    <span class="text-sm font-medium text-slate-800 dark:text-slate-200">
                        {{ $subscription->starts_at ? $subscription->starts_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Tanggal Berakhir
                    </span>
                    <span class="text-sm font-medium text-slate-800 dark:text-slate-200">
                        {{ $expiresAt ? $expiresAt->format('d M Y, H:i') : 'Tidak ditentukan (Permanen)' }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Sisa Masa Aktif
                    </span>
                    @if($expiresAt === null)
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ $hasCloud ? 'Tanpa batas waktu (Permanen)' : '-' }}
                        </span>
                    @elseif($expiresAt->isPast())
                        <span class="text-xs font-bold text-rose-600 dark:text-rose-400">
                            Kadaluarsa ({{ $expiresAt->diffForHumans() }})
                        </span>
                    @else
                        @php
                            $daysLeft = now()->diffInDays($expiresAt, false);
                        @endphp
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            Sisa {{ $daysLeft }} hari ({{ $expiresAt->diffForHumans() }})
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Metode Renewal
                    </span>
                    <span class="text-xs font-medium text-slate-800 dark:text-slate-200">
                        Manual Renewal (Non-Otomatis)
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Dibuat Pada
                    </span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ $subscription->created_at ? $subscription->created_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Terakhir Diperbarui
                    </span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ $subscription->updated_at ? $subscription->updated_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Section 2: Entitlement & Capability Matrix --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Entitlement & Devices --}}
            <div class="lg:col-span-1 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
                <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="shield" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Status Entitlement</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Akses fitur cloud dan kuota perangkat</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-4 rounded-xl {{ $hasCloud ? 'bg-emerald-50/60 dark:bg-emerald-950/40 border border-emerald-200/70 dark:border-emerald-800/60' : 'bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700' }}">
                        <div class="flex items-center gap-2 mb-1">
                            <i data-lucide="{{ $hasCloud ? 'check-circle' : 'x-circle' }}" class="w-4 h-4 {{ $hasCloud ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}"></i>
                            <span class="text-xs font-bold {{ $hasCloud ? 'text-emerald-800 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-300' }}">
                                Cloud Access: {{ $hasCloud ? 'GRANTED' : 'DENIED' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            @if($hasCloud)
                                Entitas bisnis memiliki hak akses operasional cloud penuh sesuai paket aktif.
                            @else
                                Hak akses cloud tidak aktif (paket Free, langganan nonaktif, atau telah expired).
                            @endif
                        </p>
                    </div>

                    <div class="space-y-2">
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            Limitasi Perangkat Cloud
                        </span>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-400">Batas Kuota Cloud:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ $configuredDeviceLimit > 0 ? $configuredDeviceLimit . ' Perangkat' : 'Tidak Terbatas' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-400">Perangkat Aktif Terhubung:</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ $activeDeviceCount }} Perangkat
                            </span>
                        </div>
                        @if($remainingDeviceSlots !== null)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600 dark:text-slate-400">Sisa Slot Tersedia:</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ $remainingDeviceSlots }} Slot
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Capabilities Map --}}
            <div class="lg:col-span-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
                <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="layers" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Matriks Kemampuan (Capability Map)</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Diverifikasi secara otoritatif melalui PremiumPolicy</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $capabilityLabels = [
                            'web_dashboard' => ['Web Dashboard', 'Akses pemantauan web portal'],
                            'cloud_backup' => ['Cloud Backup', 'Pencadangan data ke cloud storage'],
                            'cloud_restore' => ['Cloud Restore', 'Pemulihan basis data dari cloud'],
                            'cloud_sync' => ['Cloud Sync', 'Sinkronisasi transaksi multi-perangkat'],
                            'cloud_devices' => ['Cloud Devices', 'Otentikasi multi-kasir perangkat'],
                        ];
                    @endphp

                    @foreach($capabilityMap as $capabilityKey => $allowed)
                        @php
                            $labelInfo = $capabilityLabels[$capabilityKey] ?? [ucwords(str_replace('_', ' ', $capabilityKey)), 'Fitur platform'];
                        @endphp
                        <div class="p-3.5 rounded-xl border {{ $allowed ? 'bg-emerald-50/40 dark:bg-emerald-950/30 border-emerald-200/70 dark:border-emerald-800/60' : 'bg-slate-50/60 dark:bg-slate-800/40 border-slate-200/70 dark:border-slate-700' }} flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg {{ $allowed ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }} flex items-center justify-center shrink-0 mt-0.5">
                                <i data-lucide="{{ $allowed ? 'check' : 'x' }}" class="w-3.5 h-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $labelInfo[0] }}</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $allowed ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                        {{ $allowed ? 'Granted' : 'Denied' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 truncate">{{ $labelInfo[1] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Section 3: Aksi Administratif (Administrative Mutations) --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i data-lucide="sliders" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Aksi Administratif (Platform Override)</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Mutasi langsung status dan paket langganan secara aman</p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 flex items-start gap-3">
                <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-200">Prinsip Keamanan & Kontrak Domain:</p>
                    <p class="mt-0.5 text-[11px]">
                        Aksi di bawah ini adalah intervensi administratif khusus Platform Admin. Aksi ini tidak membuat transaksi Midtrans atau payment sukses fiktif, serta tidak menghapus data outlet, produk, atau perangkat bisnis.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                {{-- Form 1: Aktivasi / Perpanjangan Cloud --}}
                <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">
                            {{ $isCloud ? 'Perpanjang Masa Aktif Cloud (Renewal)' : 'Aktivasi Paket Cloud' }}
                        </h3>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        @if($isCloud)
                            Menambah masa aktif paket Cloud. Jika saat ini masih aktif, durasi akan ditambahkan langsung dari tanggal berakhir saat ini.
                        @else
                            Mengubah paket dari Free menjadi Cloud Aktif dengan durasi resmi dari tanggal saat ini.
                        @endif
                    </p>

                    @if($isCloud)
                        <form method="POST" action="{{ route('platform.subscriptions.renew', $subscription) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="renew_period" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Pilih Durasi Perpanjangan:</label>
                                <select
                                    id="renew_period"
                                    name="billing_period"
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                >
                                    @foreach($supportedBillingPeriods as $period)
                                        <option value="{{ $period }}">{{ ucfirst($period) }} ({{ $period === 'monthly' ? '+1 Bulan' : '+1 Tahun' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button
                                type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                <span>Perpanjang Langganan Cloud</span>
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('platform.subscriptions.activate', $subscription) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="activate_period" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Pilih Durasi Aktivasi:</label>
                                <select
                                    id="activate_period"
                                    name="billing_period"
                                    class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                >
                                    @foreach($supportedBillingPeriods as $period)
                                        <option value="{{ $period }}">{{ ucfirst($period) }} ({{ $period === 'monthly' ? '1 Bulan' : '1 Tahun' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button
                                type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                                <span>Aktifkan Paket Cloud</span>
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Form 2: Pembatasan / Downgrade --}}
                <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">
                            Penyesuaian Akses & Downgrade
                        </h3>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Pencabutan entitlement cloud melalui penurunan ke Free tier atau penonaktifan langganan.
                    </p>

                    <div class="space-y-3">
                        @if($isCloud)
                            <form
                                method="POST"
                                action="{{ route('platform.subscriptions.downgrade', $subscription) }}"
                                onsubmit="return confirm('Apakah Anda yakin ingin menurunkan paket langganan ini ke Free? Entitlement Cloud akan segera dicabut. Data bisnis dan perangkat tidak akan dihapus.');"
                            >
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-xs font-bold transition-colors shadow-xs"
                                >
                                    <i data-lucide="arrow-down-circle" class="w-3.5 h-3.5"></i>
                                    <span>Downgrade ke Paket Free</span>
                                </button>
                            </form>
                        @endif

                        @if($subscription->status === \App\Models\Subscription::STATUS_ACTIVE)
                            <form
                                method="POST"
                                action="{{ route('platform.subscriptions.inactivate', $subscription) }}"
                                onsubmit="return confirm('Apakah Anda yakin ingin mengubah status langganan menjadi Nonaktif? Entitlement Cloud akan segera dicabut.');"
                            >
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-xs font-bold transition-colors shadow-xs"
                                >
                                    <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                    <span>Set Status Nonaktif (Inactivate)</span>
                                </button>
                            </form>
                        @else
                            <p class="text-[11px] text-slate-400 italic">Langganan saat ini sudah berstatus nonaktif/expired.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts::platform>
