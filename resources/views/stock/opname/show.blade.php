<x-layouts::app :title="'Stock Opname '.$opname->code">
    <main id="mainContent" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        @php
            $isDraft = $opname->status === \App\Models\StockOpname::STATUS_DRAFT;
            $statusLabel = match ($opname->status) {
                \App\Models\StockOpname::STATUS_POSTED => 'Diposting',
                \App\Models\StockOpname::STATUS_CANCELLED => 'Dibatalkan',
                default => 'Draft',
            };
        @endphp

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('stock.opname.index') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Stock Opname</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">{{ $opname->code }}</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight font-mono">
                    {{ $opname->code }}
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $statusLabel }} · dibuka {{ $opname->created_at?->translatedFormat('d M Y · H:i') ?? '-' }}
                    @if($opname->note) · {{ $opname->note }} @endif
                </p>
            </div>
            <a href="{{ route('stock.opname.index') }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                Kembali
            </a>
        </div>

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 px-4 py-3" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0"></i>
                    <ul class="pl-4 list-disc text-xs text-rose-700 dark:text-rose-300">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('stock.opname.counts.update', ['opnameId' => $opname->id]) }}"
            class="space-y-4"
            @if(!$isDraft || !($canManageStock ?? false)) data-read-only="true" @endif
        >
            @csrf
            @method('PATCH')

            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4">Produk</th>
                                <th class="py-3.5 px-4 text-center">Stok Sistem</th>
                                <th class="py-3.5 px-4 text-center">Hitungan Fisik</th>
                                <th class="py-3.5 px-4 text-center">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @foreach($items as $index => $item)
                                @php
                                    $variance = (float) $item['variance'];
                                    $varianceClass = $variance > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : ($variance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500');
                                @endphp
                                <tr>
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-900 dark:text-white">{{ $item['product_name'] }}</p>
                                        <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $item['product_sku'] }}</p>
                                    </td>
                                    <td class="py-3 px-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ number_format((float) $item['system_stock'], 3, ',', '.') }} {{ $item['unit'] }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] }}">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="items[{{ $index }}][counted_stock]"
                                            value="{{ $item['counted_stock'] !== null ? (float) $item['counted_stock'] : '' }}"
                                            @if(!$isDraft || !($canManageStock ?? false)) disabled @endif
                                            class="w-28 h-9 px-2 text-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60"
                                        >
                                    </td>
                                    <td class="py-3 px-4 text-center tabular-nums font-bold {{ $varianceClass }}">
                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 3, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($isDraft && ($canManageStock ?? false))
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                        Simpan Hitungan
                    </button>
                </div>
            @endif
        </form>

        @if($isDraft && ($canManageStock ?? false))
            <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-2 border-t border-slate-200/80 dark:border-slate-800">
                <form method="POST" action="{{ route('stock.opname.cancel', ['opnameId' => $opname->id]) }}">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batalkan Sesi
                    </button>
                </form>
                <form method="POST" action="{{ route('stock.opname.post', ['opnameId' => $opname->id]) }}">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors">
                        Posting Opname
                    </button>
                </form>
            </div>
        @endif
    </main>
</x-layouts::app>
