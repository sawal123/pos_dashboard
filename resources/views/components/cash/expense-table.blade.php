@props([
    'expenses' => [],
])

@php
    $formatRupiah = fn ($val) => 'Rp ' . number_format($val, 0, ',', '.');
@endphp

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopExpenseTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Tanggal & Waktu</th>
                    <th scope="col" class="py-3.5 px-4">Pengeluaran</th>
                    <th scope="col" class="py-3.5 px-4">Kategori</th>
                    <th scope="col" class="py-3.5 px-4">Outlet</th>
                    <th scope="col" class="py-3.5 px-4">Shift</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Nominal</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($expenses as $expense)
                    @php
                        $statusRaw = (string) ($expense['status_raw'] ?? '');
                        $isRecorded = $statusRaw === 'recorded';
                        $statusLabel = $isRecorded
                            ? 'Tercatat'
                            : ($expense['status'] ?? ($statusRaw !== '' ? ucwords(str_replace(['_', '-'], ' ', $statusRaw)) : 'Tanpa Status'));
                        $badgeClass = $isRecorded
                            ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400'
                            : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                        $dotClass = $isRecorded ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-500';
                    @endphp
                    <tr
                        class="expense-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $expense['id'] }}"
                        data-category="{{ $expense['category_raw'] ?? $expense['category'] ?? '' }}"
                        data-outlet="{{ $expense['outlet_name'] }}"
                        data-date-raw="{{ $expense['occurred_at_raw'] }}"
                        data-raw="{{ json_encode($expense) }}"
                    >
                        {{-- 1. Tanggal & Waktu --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                {{ $expense['occurred_at'] }}
                            </span>
                        </td>

                        {{-- 2. Pengeluaran --}}
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ $expense['description'] }}</p>
                                    @if(!empty($expense['notes']))
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-xs">{{ $expense['notes'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 3. Kategori --}}
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $expense['category'] }}
                            </span>
                        </td>

                        {{-- 4. Outlet --}}
                        <td class="py-3 px-4">
                            <span class="text-slate-600 dark:text-slate-400">
                                {{ $expense['outlet_name'] }}
                            </span>
                        </td>

                        {{-- 5. Shift --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $expense['shift_number'] ?? 'Tanpa Shift' }}
                            </span>
                        </td>

                        {{-- 6. Nominal --}}
                        <td class="py-3 px-4 text-right tabular-nums font-bold text-slate-900 dark:text-white">
                            {{ $formatRupiah($expense['amount']) }}
                        </td>

                        {{-- 7. Status --}}
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $badgeClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                <span>{{ $statusLabel }}</span>
                            </span>
                        </td>

                        {{-- 8. Aksi --}}
                        <td class="py-3 px-4 text-right">
                            <button
                                type="button"
                                class="view-cash-detail-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                Detail
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
