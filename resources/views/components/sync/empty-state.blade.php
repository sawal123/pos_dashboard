@props([
    'type' => 'no-data', // 'no-data' | 'no-results'
])

<div 
    @if($type === 'no-results') id="sync-no-results" @else id="sync-no-data" @endif
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 sm:p-12 text-center shadow-sm"
>
    <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-4">
        @if($type === 'no-results')
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        @else
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        @endif
    </div>

    @if($type === 'no-results')
        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
            Riwayat Sinkronisasi Tidak Ditemukan
        </h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
            Coba ubah periode, perangkat, atau outlet yang digunakan.
        </p>
        <div class="mt-4">
            <a
                href="{{ route('sync.index') }}"
                id="sync-reset-empty"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset Filter</span>
            </a>
        </div>
    @else
        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
            Belum Ada Riwayat Sinkronisasi
        </h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
            Push request yang telah diproses dan tercatat di Cloud akan muncul di sini.
        </p>
    @endif
</div>
