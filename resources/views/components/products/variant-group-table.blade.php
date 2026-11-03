@props([
    'variantGroups' => [],
    'canManage' => false,
])

{{-- Desktop Table View --}}
<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopVariantGroupTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Nama Template</th>
                    <th scope="col" class="py-3.5 px-4">Tipe Pilihan</th>
                    <th scope="col" class="py-3.5 px-4">Opsi Varian & Harga</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Produk Terkait</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse($variantGroups as $group)
                    @php
                        $rawStatus = $group['status_raw'] ?? 'active';
                        $groupJson = base64_encode(json_encode($group));
                    @endphp
                    <tr
                        class="variant-group-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $group['id'] }}"
                        data-name="{{ $group['name'] }}"
                        data-group="{{ $groupJson }}"
                    >
                        {{-- 1. Nama Template --}}
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white text-sm">{{ $group['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                        {{ $group['is_required'] ? 'Wajib dipilih' : 'Opsional' }}
                                        @if($group['selection_type'] === 'multiple' && $group['max_selection'])
                                            &bull; Maks. {{ $group['max_selection'] }} opsi
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>

                        {{-- 2. Tipe Pilihan --}}
                        <td class="py-3.5 px-4">
                            @if($group['selection_type'] === 'single')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                                    <i data-lucide="circle-dot" class="w-3 h-3"></i>
                                    <span>Pilihan Tunggal (Radio)</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border border-purple-200/60 dark:border-purple-800/60">
                                    <i data-lucide="check-square" class="w-3 h-3"></i>
                                    <span>Pilihan Ganda (Checkbox)</span>
                                </span>
                            @endif
                        </td>

                        {{-- 3. Opsi Varian & Penyesuaian Harga --}}
                        <td class="py-3.5 px-4 max-w-xs">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse($group['options'] as $opt)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
                                        <span class="font-medium">{{ $opt['name'] }}</span>
                                        @if($opt['price_adjustment'] > 0)
                                            <span class="font-bold text-indigo-600 dark:text-indigo-400 text-[10px]">+Rp {{ number_format($opt['price_adjustment'], 0, ',', '.') }}</span>
                                        @endif
                                        @if(!empty($opt['ingredient_name']))
                                            <span class="text-[10px] text-amber-600 dark:text-amber-400" title="Memotong stok bahan: {{ $opt['ingredient_name'] }} ({{ (float)$opt['ingredient_quantity'] }} {{ $opt['ingredient_unit'] }})">📦</span>
                                        @endif
                                        @if($opt['is_default'])
                                            <span class="text-[9px] bg-slate-200 dark:bg-slate-700 px-1 rounded text-slate-500 font-bold">Def</span>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-slate-400 italic text-xs">Belum ada opsi</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- 4. Produk Terkait --}}
                        <td class="py-3.5 px-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 tabular-nums" title="{{ $group['products_summary'] }}">
                                {{ $group['products_count'] }} menu
                            </span>
                        </td>

                        {{-- 5. Status --}}
                        <td class="py-3.5 px-4 text-center">
                            @if($rawStatus === 'active')
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

                        {{-- 6. Aksi --}}
                        <td class="py-3.5 px-4 text-right">
                            @if($canManage)
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                        title="Edit Template Varian"
                                        data-edit-variant-group="{{ $groupJson }}"
                                    >
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </button>
                                    <form
                                        method="POST"
                                        action="{{ route('products.variant-groups.destroy', ['groupId' => $group['id']]) }}"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus template varian {{ addslashes($group['name']) }}? Template ini akan dilepas dari semua produk yang menggunakannya.')"
                                        class="inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                            title="Hapus Template"
                                        >
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-slate-400 text-xs italic">Lihat saja</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 mb-3">
                                <i data-lucide="sliders-horizontal" class="w-6 h-6"></i>
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white text-sm">Belum Ada Template Varian</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                Buat template varian (seperti Level Gula, Ukuran Cup, atau Topping) untuk digunakan pada berbagai menu produk.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile Cards View --}}
<div class="md:hidden space-y-3">
    @forelse($variantGroups as $group)
        @php
            $groupJson = base64_encode(json_encode($group));
        @endphp
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 dark:text-white text-sm">{{ $group['name'] }}</h4>
                        <p class="text-[11px] text-slate-400">
                            {{ $group['is_required'] ? 'Wajib dipilih' : 'Opsional' }} &bull; {{ $group['selection_type_label'] }}
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    {{ $group['products_count'] }} menu
                </span>
            </div>

            <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                @foreach($group['options'] as $opt)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        <span>{{ $opt['name'] }}</span>
                        @if($opt['price_adjustment'] > 0)
                            <span class="font-bold text-indigo-600 dark:text-indigo-400 text-[10px]">+Rp {{ number_format($opt['price_adjustment'], 0, ',', '.') }}</span>
                        @endif
                    </span>
                @endforeach
            </div>

            @if($canManage)
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        data-edit-variant-group="{{ $groupJson }}"
                    >
                        Edit
                    </button>
                    <form
                        method="POST"
                        action="{{ route('products.variant-groups.destroy', ['groupId' => $group['id']]) }}"
                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus template varian ini?')"
                        class="inline"
                    >
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="px-3 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50"
                        >
                            Hapus
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="p-8 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 text-xs">
            Belum ada template varian menu.
        </div>
    @endforelse
</div>

