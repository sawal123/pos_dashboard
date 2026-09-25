@props(['mode' => 'no-data'])

@php
    $isNoData = $mode === 'no-data';
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-8 sm:p-10 text-center space-y-4">
    <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
        <i data-lucide="{{ $isNoData ? 'washing-machine' : 'search-x' }}" class="w-6 h-6"></i>
    </div>
    <div class="space-y-1">
        <h2 class="text-base font-bold text-slate-900 dark:text-white">
            {{ $isNoData ? 'Belum Ada Pesanan Laundry' : 'Pesanan Tidak Ditemukan' }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
            {{ $isNoData
                ? 'Pesanan laundry akan tampil setelah transaksi dengan status pengerjaan tersinkron ke dashboard.'
                : 'Tidak ada pesanan yang cocok dengan filter saat ini.' }}
        </p>
    </div>
    @unless($isNoData)
        <a
            href="{{ route('laundry-orders.index') }}"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            Reset Filter
        </a>
    @endunless
</section>
