@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'description' => 'Data transaksi atau entri akan muncul di sini setelah aktivitas tercatat.',
    'actionLabel' => null,
    'actionId' => null,
])

<div class="px-5 py-12 text-center flex flex-col items-center justify-center">
    <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3 text-slate-400 dark:text-slate-500 border border-slate-200/60 dark:border-slate-700/60">
        <i data-lucide="{{ $icon }}" class="w-7 h-7"></i>
    </div>
    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 tracking-tight">{{ $title }}</h3>
    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mt-1 mb-4 leading-relaxed">{{ $description }}</p>

    @if($actionLabel)
        <button type="button" @if($actionId) id="{{ $actionId }}" @endif class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs sm:text-sm font-medium transition-all shadow-xs inline-flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ $actionLabel }}
        </button>
    @endif
</div>
