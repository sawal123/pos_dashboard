<x-layouts::platform :title="'Pengguna'">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Manajemen Pengguna Platform
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Daftar seluruh pengguna terdaftar, status verifikasi email, serta relasi dan peran pada entitas bisnis.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="users" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($users->total()) }}</strong> Pengguna</span>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.users.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                {{-- Search query input --}}
                <div class="lg:col-span-5 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari nama, email, atau nama bisnis..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Cari pengguna"
                    />
                </div>

                {{-- Account Type Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="type"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter tipe akun"
                    >
                        <option value="">Semua Tipe Akun</option>
                        <option value="platform_admin" {{ $filters['type'] === 'platform_admin' ? 'selected' : '' }}>Platform Admin</option>
                        <option value="business_user" {{ $filters['type'] === 'business_user' ? 'selected' : '' }}>User Bisnis</option>
                        <option value="unconnected" {{ $filters['type'] === 'unconnected' ? 'selected' : '' }}>Belum Terhubung</option>
                    </select>
                </div>

                {{-- Email Verification Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="verification"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter status verifikasi"
                    >
                        <option value="">Semua Verifikasi</option>
                        <option value="verified" {{ $filters['verification'] === 'verified' ? 'selected' : '' }}>Terverifikasi</option>
                        <option value="unverified" {{ $filters['verification'] === 'unverified' ? 'selected' : '' }}>Belum Verifikasi</option>
                    </select>
                </div>

                {{-- Actions --}}
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        Filter
                    </button>
                    @if($filters['q'] || $filters['type'] || $filters['verification'])
                        <a
                            href="{{ route('platform.users.index') }}"
                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs font-semibold transition-colors"
                            title="Reset Filter"
                        >
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Container --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400" aria-label="Daftar Pengguna Platform">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 select-none">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 md:px-6">Nama & Email</th>
                            <th scope="col" class="py-3.5 px-4">Tipe Akun</th>
                            <th scope="col" class="py-3.5 px-4">Email Verified</th>
                            <th scope="col" class="py-3.5 px-3 text-center">Jumlah Bisnis</th>
                            <th scope="col" class="py-3.5 px-4">Role / Relasi Bisnis</th>
                            <th scope="col" class="py-3.5 px-4">Tanggal Daftar</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($users as $user)
                            @php
                                $isPlatformAdmin = (bool) $user->is_platform_admin;
                                $hasBusiness = $user->businesses_count > 0;
                                $accountType = $isPlatformAdmin ? 'Platform Admin' : ($hasBusiness ? 'User Bisnis' : 'Belum Terhubung');
                                $isEmailVerified = $user->email_verified_at !== null;
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Nama & Email --}}
                                <td class="py-3.5 px-4 md:px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br {{ $isPlatformAdmin ? 'from-purple-600 to-indigo-700 text-white' : 'from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-slate-700 dark:text-slate-300' }} flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                            {{ $user->initials() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                                <a href="{{ route('platform.users.show', $user) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                                                    {{ $user->name }}
                                                </a>
                                            </div>
                                            <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-xs">
                                                {{ $user->email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Tipe Akun --}}
                                <td class="py-3.5 px-4">
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
                                </td>

                                {{-- Email Verified --}}
                                <td class="py-3.5 px-4">
                                    @if($isEmailVerified)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                            <i data-lucide="check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i>
                                            Terverifikasi
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                            Belum Verifikasi
                                        </span>
                                    @endif
                                </td>

                                {{-- Jumlah Bisnis --}}
                                <td class="py-3.5 px-3 text-center">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($user->businesses_count) }}
                                    </span>
                                </td>

                                {{-- Role / Relasi Bisnis --}}
                                <td class="py-3.5 px-4">
                                    @if($user->businesses->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach($user->businesses->take(2) as $business)
                                                <div class="text-[11px] truncate max-w-[200px]" title="{{ $business->name }} ({{ \App\Models\Business::roleLabel($business->pivot->role ?? '') }})">
                                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $business->name }}</span>
                                                    <span class="text-slate-400 dark:text-slate-500">({{ \App\Models\Business::roleLabel($business->pivot->role ?? '') }})</span>
                                                </div>
                                            @endforeach
                                            @if($user->businesses_count > 2)
                                                <span class="inline-block text-[10px] text-slate-400 dark:text-slate-500 font-medium">
                                                    +{{ $user->businesses_count - 2 }} bisnis lain
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>

                                {{-- Tanggal Daftar --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $user->created_at ? $user->created_at->format('d M Y') : '-' }}
                                </td>

                                {{-- Aksi --}}
                                <td class="py-3.5 px-4 text-right">
                                    <a
                                        href="{{ route('platform.users.show', $user) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/60 text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition-colors"
                                    >
                                        <span>Detail</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 px-4 text-center">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="inbox" class="w-6 h-6"></i>
                                    </div>
                                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
                                        @if($filters['q'] || $filters['type'] || $filters['verification'])
                                            Tidak ada pengguna yang cocok dengan filter pencarian.
                                        @else
                                            Belum ada pengguna terdaftar di sistem.
                                        @endif
                                    </p>
                                    @if($filters['q'] || $filters['type'] || $filters['verification'])
                                        <div class="mt-2">
                                            <a href="{{ route('platform.users.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                                Reset filter pencarian
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            @if($users->hasPages())
                <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                    {{ $users->links() }}
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
