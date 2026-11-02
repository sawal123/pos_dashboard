<x-layouts::app :title="'Stock Opname'">
    <main id="mainContent" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <a href="{{ route('stock.index') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Stok</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Stock Opname</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Stock Opname
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Hitung stok fisik dan posting selisihnya sebagai pergerakan stok.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-stock.tabs active="history" />
            </div>
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
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Tindakan tidak dapat diproses.</p>
                        <ul class="pl-4 list-disc text-xs text-rose-700 dark:text-rose-300">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Summary --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            @foreach([
                ['label' => 'Total Sesi', 'value' => $summary['total'], 'icon' => 'clipboard-list', 'class' => 'text-slate-900 dark:text-white'],
                ['label' => 'Draft', 'value' => $summary['draft'], 'icon' => 'pencil', 'class' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Diposting', 'value' => $summary['posted'], 'icon' => 'check-circle', 'class' => 'text-emerald-600 dark:text-emerald-400'],
                ['label' => 'Baris Berselisih', 'value' => $summary['variance_items'], 'icon' => 'git-compare', 'class' => 'text-rose-600 dark:text-rose-400'],
            ] as $card)
                <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $card['label'] }}</span>
                        <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4 text-slate-400"></i>
                    </div>
                    <p class="mt-3 text-xl sm:text-2xl font-extrabold tabular-nums {{ $card['class'] }}">
                        {{ number_format((int) $card['value'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </div>

        @if($canManageStock ?? false)
            <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
                <form method="POST" action="{{ route('stock.opname.store') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label for="opnameNote" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Catatan sesi (opsional)</label>
                        <input
                            id="opnameNote"
                            name="note"
                            type="text"
                            maxlength="500"
                            value="{{ old('note') }}"
                            placeholder="mis. Opname mingguan bahan baku"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Buka Sesi Opname
                    </button>
                </form>
            </div>
        @endif

        @if($items->total() > 0)
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4">Kode</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-center">Item</th>
                                <th class="py-3.5 px-4 text-center">Berselisih</th>
                                <th class="py-3.5 px-4">Dibuka</th>
                                <th class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @foreach($items as $opname)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ $opname['code'] }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold
                                            {{ $opname['status_raw'] === 'posted' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : ($opname['status_raw'] === 'cancelled' ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400') }}">
                                            {{ $opname['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center tabular-nums">{{ $opname['items_count'] }}</td>
                                    <td class="py-3 px-4 text-center tabular-nums {{ $opname['variance_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-500' }}">
                                        {{ $opname['variance_count'] }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-500 dark:text-slate-400">{{ $opname['created_at'] ?? '-' }}</td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="{{ route('stock.opname.show', ['opnameId' => $opname['id']]) }}" class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors">
                                            Buka
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($items->hasPages())
                <div>{{ $items->links() }}</div>
            @endif
        @else
            <div class="p-10 rounded-2xl bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Belum ada sesi opname</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Buka sesi baru untuk membandingkan stok sistem dengan stok fisik.</p>
            </div>
        @endif
    </main>
</x-layouts::app>
