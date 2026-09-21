@props([
    'title' => '',
])

<div class="sidebar-section mb-3">
    @if($title)
        <p class="sidebar-section-label text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-1.5 select-none">
            {{ $title }}
        </p>
    @endif
    <div class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
