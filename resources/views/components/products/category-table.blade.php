@props([
    'categories' => [],
])

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopCategoryTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Nama Kategori</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Jumlah Item</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($categories as $cat)
                    <tr
                        class="category-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $cat['id'] }}"
                        data-name="{{ $cat['name'] }}"
                        data-status="{{ $cat['status'] }}"
                    >
                        {{-- 1. Nama Kategori --}}
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ $cat['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">ID: CAT-{{ str_pad($cat['id'], 3, '0', STR_PAD_LEFT) }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- 2. Jumlah Item --}}
                        <td class="py-3.5 px-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 tabular-nums">
                                {{ $cat['items_count'] }} item
                            </span>
                        </td>

                        {{-- 3. Status --}}
                        <td class="py-3.5 px-4 text-center">
                            @if($cat['status'] === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Aktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @endif
                        </td>

                        {{-- 4. Aksi --}}
                        <td class="py-3.5 px-4 text-right">
                            <button
                                type="button"
                                onclick="showToast('info', 'Pengelolaan kategori akan tersedia setelah integrasi data.')"
                                class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                Kelola
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
