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
                        Area kendali terpusat untuk operator global NexaPOS. Fondasi arsitektur Platform Admin aktif, terisolasi penuh dari role bisnis dan multi-tenant context.
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

        {{-- Status Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">

            {{-- Card 1: Operator Identity --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                            <i data-lucide="user-check" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                            Terautentikasi
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Operator Aktif
                    </h2>
                    <p class="text-base font-bold text-slate-900 dark:text-white truncate">
                        {{ $user->name }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                        {{ $user->email }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i data-lucide="key" class="w-3.5 h-3.5 text-indigo-500"></i>
                    <span>Hak Akses: <strong class="text-slate-700 dark:text-slate-200">Global Platform Admin</strong></span>
                </div>
            </div>

            {{-- Card 2: Tenant Decoupling --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            <i data-lucide="layers" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-400 border border-purple-200/60 dark:border-purple-800/60">
                            Decoupled
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Konteks Bisnis
                    </h2>
                    <p class="text-base font-bold text-slate-900 dark:text-white">
                        Independen / Zero Tenant
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        Akses tidak membutuhkan membership <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-mono text-[11px]">business_user</code>.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-500"></i>
                    <span>Bebas dari Business Context Session</span>
                </div>
            </div>

            {{-- Card 3: Server-side Security Guard --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                            <i data-lucide="lock" class="w-5 h-5"></i>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                            Default Deny
                        </span>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Proteksi Otorisasi
                    </h2>
                    <p class="text-base font-bold text-slate-900 dark:text-white">
                        EnsurePlatformAdmin
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        Owner, Member, dan Pengguna umum ditolak dengan status HTTP 403.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i data-lucide="shield" class="w-3.5 h-3.5 text-blue-500"></i>
                    <span>Enforced Server-side Route Guard</span>
                </div>
            </div>

        </div>

        {{-- Foundation Overview Notice --}}
        <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xs">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 mt-0.5">
                    <i data-lucide="info" class="w-5 h-5"></i>
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        Fondasi Platform Admin Berhasil Diinisialisasi
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        Task <strong>ADMIN-01</strong> membatasi scope pada fondasi inti: schema flag <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-mono text-[11px]">users.is_platform_admin</code>, middleware otorisasi level server, namespace rute <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-mono text-[11px]">/platform</code>, dan layout UI terisolasi.
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-500 leading-relaxed">
                        Fitur lanjutan seperti Manajemen Merchant, Pengelolaan Langganan, Billing/Midtrans, Pemantauan Sinkronisasi, dan Analitik akan diintegrasikan pada task berikutnya (ADMIN-02 hingga ADMIN-17).
                    </p>
                </div>
            </div>
        </div>

    </div>
</x-layouts::platform>
