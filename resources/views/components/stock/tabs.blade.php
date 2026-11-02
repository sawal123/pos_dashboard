@props([
    'active' => 'stock',
])

<div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 w-fit">
    <a
        href="{{ route('stock.index') }}"
        @if($active === 'stock') aria-current="page" @endif
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 {{ $active === 'stock' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}"
    >
        Daftar Stok
    </a>
    <a
        href="{{ route('stock.history') }}"
        @if($active === 'history') aria-current="page" @endif
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 {{ $active === 'history' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}"
    >
        Riwayat Stok
    </a>
</div>
