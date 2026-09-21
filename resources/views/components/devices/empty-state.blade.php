@props([
    'mode' => 'no-data', // 'no-data', 'no-results'
])

@if($mode === 'no-data')
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-10 text-center space-y-4">
        <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="monitor-smartphone" class="w-7 h-7"></i>
        </div>
        <div class="max-w-md mx-auto space-y-1">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                Belum Ada Perangkat
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Perangkat yang terdaftar di Cloud akan muncul di sini.
            </p>
        </div>
    </div>
@elseif($mode === 'no-results')
    <div id="deviceFilterEmptyState" class="hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-8 text-center space-y-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
            <i data-lucide="search-x" class="w-6 h-6"></i>
        </div>
        <div class="max-w-xs mx-auto space-y-1">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">
                Perangkat Tidak Ditemukan
            </h4>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Coba ubah kata pencarian atau filter yang digunakan.
            </p>
        </div>
    </div>
@endif
