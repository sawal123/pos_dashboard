@props([
    'ledgers' => [],
])

@php
    $formatRupiah = fn ($val) => 'Rp ' . number_format($val, 0, ',', '.');
@endphp

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopCashLedgerTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Tanggal & Waktu</th>
                    <th scope="col" class="py-3.5 px-4">Jenis</th>
                    <th scope="col" class="py-3.5 px-4">Kategori</th>
                    <th scope="col" class="py-3.5 px-4">Outlet</th>
                    <th scope="col" class="py-3.5 px-4">Shift</th>
                    <th scope="col" class="py-3.5 px-4">Referensi</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Nominal</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
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
                    <tr
                        class="cash-ledger-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $ledger['id'] }}"
                        data-type="{{ $typeRaw }}"
                        data-category="{{ $ledger['category_raw'] ?? $ledger['category'] ?? '' }}"
                        data-outlet="{{ $ledger['outlet_name'] }}"
                        data-date-raw="{{ $ledger['occurred_at_raw'] }}"
                        data-raw="{{ json_encode($ledger) }}"
                    >
                        {{-- 1. Tanggal & Waktu --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                {{ $ledger['occurred_at'] }}
                            </span>
                        </td>

                        {{-- 2. Jenis --}}
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $badgeClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                <span>{{ $typeLabel }}</span>
                            </span>
                        </td>

                        {{-- 3. Kategori --}}
                        <td class="py-3 px-4">
                            <span class="text-slate-800 dark:text-slate-200 font-semibold">
                                {{ $ledger['category'] }}
                            </span>
                        </td>

                        {{-- 4. Outlet --}}
                        <td class="py-3 px-4">
                            <span class="text-slate-600 dark:text-slate-400">
                                {{ $ledger['outlet_name'] }}
                            </span>
                        </td>

                        {{-- 5. Shift --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $ledger['shift_number'] ?? 'Tanpa Shift' }}
                            </span>
                        </td>

                        {{-- 6. Referensi --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                {{ $ledger['reference_id'] ?? '-' }}
                            </span>
                        </td>

                        {{-- 7. Nominal --}}
                        <td class="py-3 px-4 text-right tabular-nums {{ $amountClass }}">
                            {{ $sign ? $sign . ' ' : '' }}{{ $formatRupiah($ledger['amount']) }}
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
