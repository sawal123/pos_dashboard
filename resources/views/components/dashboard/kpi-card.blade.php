@props([
    'title' => '',
    'value' => '',
    'icon' => 'trending-up',
    'trend' => null,
    'trendUp' => true,
    'subtitle' => null,
    'accent' => 'indigo',
])

@php
    $accentStyles = [
        'indigo' => [
            'iconBg' => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-indigo-100 dark:border-indigo-900/40',
            'borderHover' => 'hover:border-indigo-200 dark:hover:border-indigo-800',
        ],
        'emerald' => [
            'iconBg' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-900/40',
            'borderHover' => 'hover:border-emerald-200 dark:hover:border-emerald-800',
        ],
        'blue' => [
            'iconBg' => 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border-blue-100 dark:border-blue-900/40',
            'borderHover' => 'hover:border-blue-200 dark:hover:border-blue-800',
        ],
        'amber' => [
            'iconBg' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-900/40',
            'borderHover' => 'hover:border-amber-200 dark:hover:border-amber-800',
        ],
        'rose' => [
            'iconBg' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border-rose-100 dark:border-rose-900/40',
            'borderHover' => 'hover:border-rose-200 dark:hover:border-rose-800',
        ],
    ];

    $currentAccent = $accentStyles[$accent] ?? $accentStyles['indigo'];
@endphp

<div class="group bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 {{ $currentAccent['borderHover'] }} transition-all duration-200 shadow-xs hover:shadow-md hover:-translate-y-0.5">
    <div class="flex items-center justify-between gap-3 mb-3">
        <span class="text-xs font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase">{{ $title }}</span>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center border {{ $currentAccent['iconBg'] }} transition-transform duration-200 group-hover:scale-105">
            <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
        </div>
    </div>

    <div class="flex items-baseline justify-between gap-2 flex-wrap">
        <p class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white tabular-nums">{{ $value }}</p>

        @if($trend !== null)
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full {{ $trendUp ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/50' : 'text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/50' }}">
                <i data-lucide="{{ $trendUp ? 'trending-up' : 'trending-down' }}" class="w-3 h-3"></i>
                {{ $trend }}
            </span>
        @endif
    </div>

    @if($subtitle)
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 flex items-center gap-1.5">
            {{ $subtitle }}
        </p>
    @endif
</div>
