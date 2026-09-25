@props(['subscription'])

@php
    $cloudActive = (bool) ($subscription['cloud_access'] ?? false);

    $cloudBadgeClass = $cloudActive
        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="space-y-1">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Akses Cloud</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Status akses dihitung dari aturan server: paket harus <span class="font-semibold">Cloud</span>,
                status langganan <span class="font-semibold">Aktif</span>, dan tanggal kedaluwarsa belum terlewat.
            </p>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $cloudBadgeClass }}">
            {{ $cloudActive ? 'Aktif' : 'Tidak Aktif' }}
        </span>
    </div>

    <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
        <li class="flex items-start gap-2">
            <i data-lucide="refresh-cw" class="w-4 h-4 shrink-0 text-indigo-500 dark:text-indigo-400"></i>
            <span>Sinkronisasi Cloud (push/pull dan registrasi perangkat) memakai status akses ini.</span>
        </li>
        <li class="flex items-start gap-2">
            <i data-lucide="user-check" class="w-4 h-4 shrink-0 text-indigo-500 dark:text-indigo-400"></i>
            <span>Akses Cloud tetap bergantung pada autentikasi, membership bisnis, dan perangkat aktif.</span>
        </li>
    </ul>

    <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
        <p class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
            Pembelian dan perpanjangan otomatis belum tersedia. Halaman ini hanya menampilkan status langganan —
            tidak ada checkout, invoice, atau tombol pembayaran. Hubungi administrator untuk perubahan paket.
        </p>
    </div>
</section>
