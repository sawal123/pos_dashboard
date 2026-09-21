@props([
    'activeTab' => 'ledgers',
    'ledgers' => [],
    'expenses' => [],
])

@php
    $formatRupiah = fn ($val) => 'Rp ' . number_format($val, 0, ',', '.');
@endphp

@if($activeTab === 'ledgers')
    {{-- 1. Mobile Cards for Cash Ledger --}}
    <div id="mobileCashLedgerCards" class="md:hidden space-y-3">
        @foreach($ledgers as $ledger)
            @php
                $typeRaw = $ledger['type_raw'] ?? $ledger['type'] ?? '';
                $isIn = $typeRaw === 'in';
                $isOut = $typeRaw === 'out';
                $typeLabel = $ledger['type'] ?? ($isIn ? 'Kas Masuk' : ($isOut ? 'Kas Keluar' : ucfirst($typeRaw)));
                $sign = $isIn ? '+' : ($isOut ? '-' : '');
                $badgeClass = $isIn
                    ? 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400'
                    : ($isOut
                        ? 'bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400'
                        : 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300');
                $dotClass = $isIn ? 'bg-emerald-500' : ($isOut ? 'bg-rose-500' : 'bg-slate-400');
                $amountClass = $isIn
                    ? 'text-emerald-600 dark:text-emerald-400 font-extrabold'
                    : ($isOut
                        ? 'text-rose-600 dark:text-rose-400 font-extrabold'
                        : 'text-slate-700 dark:text-slate-300 font-extrabold');
            @endphp
            <div
                class="cash-ledger-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
                data-id="{{ $ledger['id'] }}"
                data-type="{{ $typeRaw }}"
                data-category="{{ $ledger['category_raw'] ?? $ledger['category'] ?? '' }}"
                data-outlet="{{ $ledger['outlet_name'] }}"
                data-date-raw="{{ $ledger['occurred_at_raw'] }}"
                data-raw="{{ json_encode($ledger) }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $badgeClass }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                            <span>{{ $typeLabel }}</span>
                        </span>
                        <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                            {{ $ledger['occurred_at'] }}
                        </p>
                    </div>
                    <span class="text-base tabular-nums {{ $amountClass }} shrink-0">
                        {{ $sign ? $sign . ' ' : '' }}{{ $formatRupiah($ledger['amount']) }}
                    </span>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1">
                    <p class="font-bold text-slate-800 dark:text-slate-200 text-xs">
                        {{ $ledger['category'] }}
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ $ledger['outlet_name'] }} · <span class="font-mono">{{ $ledger['shift_number'] ?? 'Tanpa Shift' }}</span>
                    </p>
                    @if(!empty($ledger['reference_id']))
                        <p class="font-mono text-[11px] text-indigo-600 dark:text-indigo-400">
                            {{ $ledger['reference_id'] }}
                        </p>
                    @endif
                    @if(!empty($ledger['note']))
                        <p class="text-[11px] italic text-slate-500 dark:text-slate-400 line-clamp-1">
                            {{ $ledger['note'] }}
                        </p>
                    @endif
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        type="button"
                        class="view-cash-detail-btn w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Lihat Detail
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@else
    {{-- 2. Mobile Cards for Expenses --}}
    <div id="mobileExpenseCards" class="md:hidden space-y-3">
        @foreach($expenses as $expense)
            @php
                $statusRaw = $expense['status_raw'] ?? $expense['status'] ?? 'recorded';
                $statusLabel = $expense['status'] ?? ($statusRaw === 'recorded' ? 'Tercatat' : ucwords(str_replace('_', ' ', $statusRaw)));
            @endphp
            <div
                class="expense-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
                data-id="{{ $expense['id'] }}"
                data-category="{{ $expense['category_raw'] ?? $expense['category'] ?? '' }}"
                data-outlet="{{ $expense['outlet_name'] }}"
                data-date-raw="{{ $expense['occurred_at_raw'] }}"
                data-raw="{{ json_encode($expense) }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{{ $statusLabel }}</span>
                        </span>
                        <p class="font-bold text-slate-900 dark:text-white text-xs mt-1 truncate">
                            {{ $expense['description'] }}
                        </p>
                        <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $expense['occurred_at'] }}
                        </p>
                    </div>
                    <span class="text-base tabular-nums font-bold text-slate-900 dark:text-white shrink-0">
                        {{ $formatRupiah($expense['amount']) }}
                    </span>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {{ $expense['category'] }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ $expense['outlet_name'] }} · <span class="font-mono">{{ $expense['shift_number'] ?? 'Tanpa Shift' }}</span>
                    </p>
                    @if(!empty($expense['notes']))
                        <p class="text-[11px] italic text-slate-500 dark:text-slate-400 line-clamp-1">
                            {{ $expense['notes'] }}
                        </p>
                    @endif
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        type="button"
                        class="view-cash-detail-btn w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Lihat Detail
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
