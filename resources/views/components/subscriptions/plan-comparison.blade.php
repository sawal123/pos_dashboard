@props(['subscription'])

@php
    $isCloudPlan = ($subscription['plan_raw'] ?? null) === 'cloud';
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="space-y-1">
        <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Perbandingan Paket</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Perbandingan ini hanya memuat perilaku yang benar-benar ditegakkan oleh server.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Free</h3>
                @unless($isCloudPlan)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                        Paket saat ini
                    </span>
                @endunless
            </div>
            <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="w-4 h-4 shrink-0 text-slate-500 dark:text-slate-400"></i>
                    <span>POS tetap dapat digunakan secara lokal pada aplikasi yang mendukung.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="x" class="w-4 h-4 shrink-0 text-rose-500 dark:text-rose-400"></i>
                    <span>Tidak mendapat akses sinkronisasi Cloud.</span>
                </li>
            </ul>
        </div>

        <div class="rounded-2xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/50 dark:bg-indigo-950/20 p-4 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Cloud</h3>
                @if($isCloudPlan)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800">
                        Paket saat ini
                    </span>
                @endif
            </div>
            <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                <li class="flex items-start gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4 shrink-0 text-indigo-500 dark:text-indigo-400"></i>
                    <span>Sinkronisasi Cloud tersedia hanya selama subscription benar-benar aktif.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4 shrink-0 text-indigo-500 dark:text-indigo-400"></i>
                    <span>Akses Cloud tetap bergantung pada autentikasi, membership bisnis, dan perangkat aktif.</span>
                </li>
            </ul>
        </div>
    </div>

    <p class="text-xs text-slate-500 dark:text-slate-400">
        Batas jumlah perangkat, limit transaksi, dan fitur pembayaran berbayar tidak ditampilkan karena belum
        ditegakkan oleh server.
    </p>
</section>
