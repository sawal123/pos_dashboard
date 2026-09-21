@props([
    'items' => [],
    'title' => null,
])

@php
    $crumbs = $items;
    if (empty($crumbs)) {
        $defaultTitle = $title ?? 'Dashboard';
        if (strtolower($defaultTitle) === 'dashboard') {
            $crumbs = [
                ['label' => 'Dashboard', 'url' => null],
            ];
        } else {
            $crumbs = [
                ['label' => 'Dashboard', 'url' => \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/dashboard'],
                ['label' => $defaultTitle, 'url' => null],
            ];
        }
    }
@endphp

<nav class="flex items-center gap-1.5 text-xs md:text-sm" aria-label="Breadcrumb">
    @foreach($crumbs as $index => $crumb)
        @php
            $isLast = $loop->last;
            $hasUrl = !empty($crumb['url']) && !$isLast;
        @endphp

        @if($hasUrl)
            <a
                href="{{ $crumb['url'] }}"
                wire:navigate
                class="text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium transition-colors truncate max-w-[120px] sm:max-w-[200px]"
            >
                {{ $crumb['label'] }}
            </a>
        @else
            <span
                class="{{ $isLast ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400' }} truncate max-w-[140px] sm:max-w-none"
                @if($isLast) aria-current="page" @endif
            >
                {{ $crumb['label'] }}
            </span>
        @endif

        @if(!$isLast)
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-600 shrink-0"></i>
        @endif
    @endforeach
</nav>
