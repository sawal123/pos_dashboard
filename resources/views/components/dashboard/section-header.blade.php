@props([
    'title' => '',
    'subtitle' => null,
    'icon' => null,
])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div class="flex items-center gap-2.5">
        @if($icon)
            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
            </div>
        @endif
        <div>
            <h2 class="font-semibold text-slate-900 dark:text-white text-base md:text-lg tracking-tight">{{ $title }}</h2>
            @if($subtitle)
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if(isset($actions) && $actions->isNotEmpty())
        <div class="flex items-center gap-2 flex-wrap">
            {{ $actions }}
        </div>
    @endif
</div>
