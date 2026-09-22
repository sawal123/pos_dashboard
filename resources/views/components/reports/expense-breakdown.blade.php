@props([
    'breakdown' => [],
])

@php
    $formatRupiah = fn ($val) => 'Rp ' . number_format($val, 0, ',', '.');
@endphp

<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
            Kategori Pengeluaran
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Alokasi biaya operasional menurut pos pengeluaran.
        </p>
    </div>

    {{-- Breakdown List --}}
    @if(count($breakdown) > 0)
        <div id="expenseBreakdownContainer" class="space-y-3.5 pt-1">
            @foreach($breakdown as $item)
                <div class="space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                        <span class="font-bold text-slate-900 dark:text-white">
                            {{ $item['category'] }}
                        </span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-400">
                            {{ $item['expense_count'] }} pengeluaran ({{ $item['percentage'] }}%) · {{ $formatRupiah($item['total_amount']) }}
                        </span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-amber-500 transition-all duration-300"
                            style="width: {{ $item['percentage'] }}%;"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div id="expenseBreakdownEmpty" class="py-8 text-center text-xs text-slate-400 italic">
            Tidak ada data pengeluaran pada periode ini.
        </div>
    @endif
</div>
