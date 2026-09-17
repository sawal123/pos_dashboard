@props([
    'status' => 'synced',
    'label' => null,
    'size' => 'sm',
])

@php
    $status = strtolower($status);
    $configs = [
        'synced' => [
            'label' => 'Synced',
            'dot' => 'bg-emerald-500',
            'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400',
        ],
        'pending' => [
            'label' => 'Pending',
            'dot' => 'bg-amber-500',
            'badge' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200/80 dark:border-amber-800/60 text-amber-700 dark:text-amber-400',
        ],
        'failed' => [
            'label' => 'Failed',
            'dot' => 'bg-rose-500',
            'badge' => 'bg-rose-50 dark:bg-rose-950/50 border-rose-200/80 dark:border-rose-800/60 text-rose-700 dark:text-rose-400',
        ],
        'offline' => [
            'label' => 'Offline',
            'dot' => 'bg-slate-400',
            'badge' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
        ],
        'paid' => [
            'label' => 'Lunas',
            'dot' => 'bg-emerald-500',
            'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400',
        ],
        'active' => [
            'label' => 'Aktif',
            'dot' => 'bg-emerald-500',
            'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400',
        ],
    ];

    $cfg = $configs[$status] ?? [
        'label' => ucfirst($status),
        'dot' => 'bg-slate-400',
        'badge' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300',
    ];

    $displayText = $label ?? $cfg['label'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold {{ $cfg['badge'] }}">
    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $cfg['dot'] }}"></span>
    <span>{{ $displayText }}</span>
</span>
