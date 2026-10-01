<x-layouts::platform :title="'Detail Pengguna: ' . $user->name">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @php
            $isPlatformAdmin = (bool) $user->is_platform_admin;
            $hasBusiness = $user->businesses_count > 0;
            $accountType = $isPlatformAdmin ? 'Platform Admin' : ($hasBusiness ? 'User Bisnis' : 'Belum Terhubung');
            $isEmailVerified = $user->email_verified_at !== null;
        @endphp

        {{-- Back Navigation & Page Header --}}
        <div class="flex flex-col gap-3">
            <div>
                <a
                    href="{{ route('platform.users.index') }}"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Kembali ke Daftar Pengguna</span>
                </a>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br {{ $isPlatformAdmin ? 'from-purple-600 to-indigo-700 text-white' : 'from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-slate-700 dark:text-slate-200' }} flex items-center justify-center font-black text-lg shrink-0 shadow-xs">
                        {{ $user->initials() }}
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($isPlatformAdmin)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800/60">
                                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400"></i>
                                    Platform Admin
                                </span>
                            @elseif($hasBusiness)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                                    User Bisnis
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60">
                                    <i data-lucide="link-2-off" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400"></i>
                                    Belum Terhubung
                                </span>
                            @endif

                            @if($isEmailVerified)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <i data-lucide="check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i>
                                    Email Terverifikasi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                    Belum Verifikasi
                                </span>
                            @endif

                            <span class="font-mono text-xs text-slate-400 dark:text-slate-500">
                                ID: #{{ $user->id }}
                            </span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ $user->name }}
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 1: Informasi Identitas Akun --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Informasi Akun & Identitas</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Metadata autentikasi dan status pengguna pada sistem</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Nama Lengkap
                    </span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">
                        {{ $user->name }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Alamat Email
                    </span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white font-mono">
                        {{ $user->email }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        User ID
                    </span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white font-mono">
                        #{{ $user->id }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Status Verifikasi Email
                    </span>
                    @if($isEmailVerified)
                        <div class="space-y-0.5">
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                Terverifikasi
                            </span>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $user->email_verified_at->format('d M Y, H:i') }}
                            </p>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                            Belum Terverifikasi
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Hak Akses Platform Admin
                    </span>
                    @if($isPlatformAdmin)
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-purple-600 dark:text-purple-400">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                            Platform Admin (Operator Global)
                        </span>
                    @else
                        <span class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                            Pengguna Biasa
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Jumlah Bisnis Terhubung
                    </span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">
                        {{ number_format($user->businesses_count) }} Bisnis
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Tanggal Pendaftaran
                    </span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ $user->created_at ? $user->created_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Terakhir Diperbarui
                    </span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ $user->updated_at ? $user->updated_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Section 2: Membership Bisnis --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="building-2" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Keanggotaan Bisnis ({{ $user->businesses_count }})</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Daftar seluruh bisnis yang diikuti beserta peran otorisasi keanggotaan</p>
                    </div>
                </div>
            </div>

            @if($user->businesses->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400" aria-label="Keanggotaan Bisnis Pengguna">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 select-none">
                            <tr>
                                <th scope="col" class="py-3.5 px-4 md:px-6">Nama Bisnis & Slug</th>
                                <th scope="col" class="py-3.5 px-4">Tipe Bisnis</th>
                                <th scope="col" class="py-3.5 px-4">Peran (Role)</th>
                                <th scope="col" class="py-3.5 px-4">Status Bisnis</th>
                                <th scope="col" class="py-3.5 px-4">Bergabung</th>
                                <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($user->businesses as $business)
                                @php
                                    $role = $business->pivot->role ?? '';
                                    $roleLabel = \App\Models\Business::roleLabel($role);
                                    $isOwner = $role === \App\Models\Business::ROLE_OWNER;
                                @endphp
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3.5 px-4 md:px-6">
                                        <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                            <a href="{{ route('platform.businesses.show', $business) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                                                {{ $business->name }}
                                            </a>
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-xs">
                                            {{ $business->slug }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            {{ $business->businessTypeLabel() }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $isOwner ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60' : 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60' }}">
                                            @if($isOwner)
                                                <i data-lucide="crown" class="w-3 h-3 text-amber-600 dark:text-amber-400"></i>
                                            @else
                                                <i data-lucide="user" class="w-3 h-3 text-indigo-600 dark:text-indigo-400"></i>
                                            @endif
                                            {{ $roleLabel }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $business->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $business->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $business->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        {{ $business->pivot->created_at ? \Illuminate\Support\Carbon::parse($business->pivot->created_at)->format('d M Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <a
                                            href="{{ route('platform.businesses.show', $business) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/60 text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition-colors"
                                        >
                                            <span>Lihat Bisnis</span>
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 px-4 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="link-2-off" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
                        Pengguna ini belum terhubung ke entitas bisnis mana pun.
                    </p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-md mx-auto">
                        Akun ini belum memiliki kepemilikan bisnis dan belum ditambahkan sebagai anggota (staf/kasir) pada entitas bisnis terdaftar.
                    </p>
                </div>
            @endif
        </div>

        {{-- Platform Security Policy Notice --}}
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 flex items-start gap-3">
            <i data-lucide="shield-alert" class="w-4 h-4 text-slate-400 dark:text-slate-500 shrink-0 mt-0.5"></i>
            <div class="space-y-1">
                <p class="font-semibold text-slate-700 dark:text-slate-300">Kebijakan Keamanan & Manajemen Akun</p>
                <p>
                    Sesuai prinsip isolasi platform dan hak akses terkontrol, status Platform Admin dan penetapan peran keanggotaan bisnis bersifat read-only pada modul ini untuk mencegah eskalasi hak istimewa yang tidak disengaja.
                </p>
            </div>
        </div>

    </div>
</x-layouts::platform>
