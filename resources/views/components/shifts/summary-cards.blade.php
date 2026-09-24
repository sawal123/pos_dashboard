@props(['summary'])

@php
    $cards = [
        ['id' => 'shiftSummaryTotal', 'label' => 'Total Shift', 'value' => $summary['total_shifts'] ?? 0, 'icon' => 'clock-3', 'class' => 'text-slate-700 dark:text-slate-200'],
        ['id' => 'shiftSummaryOpen', 'label' => 'Open', 'value' => $summary['open_shifts'] ?? 0, 'icon' => 'play-circle', 'class' => 'text-emerald-700 dark:text-emerald-300'],
        ['id' => 'shiftSummaryClosed', 'label' => 'Closed', 'value' => $summary['closed_shifts'] ?? 0, 'icon' => 'check-circle-2', 'class' => 'text-indigo-700 dark:text-indigo-300'],
    ];
@endphp

<section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    @foreach($cards as $card)
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p id="{{ $card['id'] }}" class="mt-1 text-2xl font-extrabold tabular-nums {{ $card['class'] }}">
                        {{ number_format((int) $card['value'], 0, ',', '.') }}
                    </p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <i data-lucide="{{ $card['icon'] }}" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
    @endforeach
</section>
