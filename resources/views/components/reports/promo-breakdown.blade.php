@props([
    'breakdown' => [],
])

<div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
    <div class="flex items-center justify-between gap-2 mb-4">
        <div>
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Performa Promo</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Pemakaian dan diskon per promo</p>
        </div>
        <i data-lucide="badge-percent" class="w-4 h-4 text-slate-400"></i>
    </div>

    @if(count($breakdown) === 0)
        <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6 italic">
            Belum ada promo yang terpakai pada periode ini.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="py-2.5 px-3">Promo</th>
                        <th class="py-2.5 px-3 text-center">Pemakaian</th>
                        <th class="py-2.5 px-3 text-right">Diskon</th>
                        <th class="py-2.5 px-3 text-right">Omzet</th>
                        <th class="py-2.5 px-3 text-right">Rata-rata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($breakdown as $promo)
                        <tr>
                            <td class="py-2.5 px-3 font-semibold text-slate-800 dark:text-slate-200">{{ $promo['promo_name'] }}</td>
                            <td class="py-2.5 px-3 text-center tabular-nums">{{ number_format((int) $promo['redeemed_count'], 0, ',', '.') }}</td>
                            <td class="py-2.5 px-3 text-right tabular-nums text-rose-600 dark:text-rose-400">
                                Rp {{ number_format((int) $promo['total_discount'], 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-right tabular-nums">
                                Rp {{ number_format((int) $promo['revenue_after_discount'], 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-right tabular-nums">
                                Rp {{ number_format((int) $promo['avg_basket'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
