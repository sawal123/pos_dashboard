<x-layouts::app :title="__('Dashboard')">
    <main class="flex min-h-[calc(100vh-4rem)] items-center justify-center p-4 md:p-8">
        <section class="w-full max-w-2xl rounded-3xl border border-slate-200/90 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-10">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                <i data-lucide="lock-keyhole" class="h-8 w-8"></i>
            </div>

            <div class="mx-auto mt-6 max-w-xl space-y-3">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200/80 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300">
                    <i data-lucide="cloud-off" class="h-3.5 w-3.5"></i>
                    Akses Cloud belum aktif
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white md:text-3xl">
                    Dashboard Anda siap digunakan
                </h1>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400 md:text-base">
                    {{ $businessName ? "Bisnis {$businessName} belum memiliki langganan Cloud aktif." : 'Bisnis Anda belum memiliki langganan Cloud aktif.' }}
                    Berlangganan untuk membuka dashboard, sinkronisasi, laporan, dan seluruh fitur operasional.
                </p>
            </div>

            @if(session('status'))
                <div class="mx-auto mt-6 flex max-w-xl items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-left dark:border-amber-900/60 dark:bg-amber-950/20" role="status">
                    <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                    <p class="text-sm text-amber-800 dark:text-amber-200">{{ session('status') }}</p>
                </div>
            @endif

            <div class="mx-auto mt-8 grid max-w-xl gap-3 text-left sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                    <i data-lucide="layout-dashboard" class="h-5 w-5 text-indigo-600 dark:text-indigo-400"></i>
                    <p class="mt-3 text-xs font-semibold text-slate-800 dark:text-slate-200">Dashboard lengkap</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">Pantau performa bisnis dalam satu tampilan.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                    <i data-lucide="refresh-cw" class="h-5 w-5 text-indigo-600 dark:text-indigo-400"></i>
                    <p class="mt-3 text-xs font-semibold text-slate-800 dark:text-slate-200">Sinkronisasi Cloud</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">Jaga data tetap terhubung di seluruh perangkat.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                    <i data-lucide="shield-check" class="h-5 w-5 text-indigo-600 dark:text-indigo-400"></i>
                    <p class="mt-3 text-xs font-semibold text-slate-800 dark:text-slate-200">Akses aman</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">Kelola akses tim dan perangkat bisnis.</p>
                </div>
            </div>

            <button
                type="button"
                onclick="window.openSubscriptionGate && window.openSubscriptionGate()"
                class="mt-8 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 transition-all hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
            >
                <i data-lucide="sparkles" class="h-4 w-4"></i>
                Lihat paket Cloud
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
        </section>
    </main>

    <x-ui.subscription-gate-modal :autoOpen="true" />
</x-layouts::app>
