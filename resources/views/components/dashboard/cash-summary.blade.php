@props([
    'cashIn' => 'Rp 0',
    'cashOut' => 'Rp 0',
    'netMovement' => 'Rp 0',
    'recordedExpense' => 'Rp 0',
    'openShiftsCount' => 0,
    'openShiftsLabel' => 'Tidak ada shift terbuka',
])

@php
    $isOpen = $openShiftsCount > 0;
    $badgeClass = $isOpen
        ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400'
        : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
    $dotClass = $isOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400';
@endphp

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-100 dark:border-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-base tracking-tight">Ringkasan Pergerakan Kas</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Mutasi kas & pengeluaran hari ini</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold {{ $badgeClass }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                    {{ $openShiftsLabel }}
                </span>
            </div>
        </div>

        {{-- 4 Metrics Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            {{-- Kas Masuk --}}
            <div class="p-3.5 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/30 border border-emerald-100/80 dark:border-emerald-900/40">
                <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 mb-1">
                    <span class="text-xs font-medium uppercase tracking-wider">Kas Masuk</span>
                    <i data-lucide="arrow-down-left" class="w-3.5 h-3.5"></i>
                </div>
                <p class="text-base sm:text-lg font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">{{ $cashIn }}</p>
                <p class="text-[11px] text-emerald-600/80 dark:text-emerald-400/70 mt-0.5">Mutasi type: in</p>
            </div>

            {{-- Kas Keluar --}}
            <div class="p-3.5 rounded-xl bg-rose-50/50 dark:bg-rose-950/30 border border-rose-100/80 dark:border-rose-900/40">
                <div class="flex items-center justify-between text-rose-600 dark:text-rose-400 mb-1">
                    <span class="text-xs font-medium uppercase tracking-wider">Kas Keluar</span>
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                </div>
                <p class="text-base sm:text-lg font-bold text-rose-700 dark:text-rose-300 tabular-nums">{{ $cashOut }}</p>
                <p class="text-[11px] text-rose-600/80 dark:text-rose-400/70 mt-0.5">Mutasi type: out</p>
            </div>

            {{-- Net Pergerakan --}}
            <div class="p-3.5 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50">
                <div class="flex items-center justify-between text-indigo-600 dark:text-indigo-400 mb-1">
                    <span class="text-xs font-medium uppercase tracking-wider font-semibold">Net Pergerakan</span>
                    <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                </div>
                <p class="text-base sm:text-lg font-bold text-indigo-700 dark:text-indigo-300 tabular-nums">{{ $netMovement }}</p>
                <p class="text-[11px] text-indigo-600/80 dark:text-indigo-400/70 mt-0.5">Masuk - Keluar</p>
            </div>

            {{-- Pengeluaran Tercatat --}}
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                    <span class="text-xs font-medium uppercase tracking-wider">Pengeluaran Tercatat</span>
                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                </div>
                <p class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tabular-nums">{{ $recordedExpense }}</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Expense: recorded</p>
            </div>
        </div>
    </div>

    {{-- Footer & Actions --}}
    <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
        <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
            <i data-lucide="info" class="w-3.5 h-3.5 text-slate-400"></i>
            <span>Buku kas dan pencatatan operasional</span>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('cash.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                <span>Buka Buku Kas</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </div>
</div>
