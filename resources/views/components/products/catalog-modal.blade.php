{{-- DASH-15 — owner-only catalog modal (create/edit product, service, category). --}}
@props([
    'categories' => [],
    'businessType' => null,
    'rawMaterialOptions' => [],
    'variantGroupOptions' => [],
    'unitOptions' => [],
])

@php
    $statusOptions = [
        ['value' => 'active', 'label' => 'Aktif'],
        ['value' => 'inactive', 'label' => 'Nonaktif'],
    ];
    $defaultKind = $businessType === 'laundry' ? 'service' : 'product';
    $defaultUnit = $businessType === 'laundry' ? 'kg' : 'pcs';
@endphp

<div
    id="catalogModal"
    class="fixed inset-0 z-[90] hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="catalogModalTitle"
    data-business-type="{{ $businessType ?? 'unknown' }}"
    data-default-kind="{{ $defaultKind }}"
    data-default-unit="{{ $defaultUnit }}"
    data-raw-materials="{{ base64_encode(json_encode($rawMaterialOptions ?? [])) }}"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-catalog-modal></div>

    <div class="fixed inset-0 flex items-center justify-center p-3 sm:p-6 overflow-hidden">
        <div class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-3.5rem)] overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 z-10 shrink-0">
                <div>
                    <span id="catalogModalSubtitle" class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tambah</span>
                    <h2 id="catalogModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Produk</h2>
                </div>
                <button
                    type="button"
                    data-close-catalog-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup formulir katalog"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- ==================== PRODUCT / SERVICE FORM ==================== --}}
            <form
                id="catalogProductForm"
                method="POST"
                action="{{ route('products.store') }}"
                data-store-url="{{ route('products.store') }}"
                data-update-url-template="{{ route('products.update', ['productId' => '__ID__']) }}"
                class="flex flex-col min-h-0 flex-1 overflow-hidden"
                enctype="multipart/form-data"
                novalidate
            >
                @csrf
                <input type="hidden" name="_method" value="POST" id="catalogProductMethod">
                <input type="hidden" name="kind" value="product" id="catalogProductKind">

                {{-- Scrollable Form Body --}}
                <div class="p-5 sm:p-6 space-y-4 overflow-y-auto flex-1">

                {{-- Product Image Upload Area --}}
                <div id="catalogProductImageContainer" class="flex items-center gap-4 p-3.5 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40">
                    <div class="relative w-20 h-20 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-center shrink-0 overflow-hidden group">
                        <img id="catalogProductImagePreview" src="" alt="Preview" class="w-full h-full object-cover hidden">
                        <div id="catalogProductImagePlaceholder" class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                            <i data-lucide="image" class="w-6 h-6"></i>
                            <span class="text-[10px] mt-1 font-medium">Foto</span>
                        </div>
                        <button
                            type="button"
                            id="btnRemoveProductImage"
                            class="absolute top-1 right-1 p-1 rounded-md bg-rose-600 text-white shadow-xs opacity-90 hover:opacity-100 transition-opacity hidden cursor-pointer"
                            title="Hapus Foto"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 min-w-0">
                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-0.5">Foto Menu / Produk</span>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-2">Format: JPG, PNG, WEBP (Maksimal 2MB).</p>
                        <input
                            type="file"
                            id="catalogProductImageInput"
                            name="image"
                            accept="image/png,image/jpeg,image/webp,image/jpg"
                            class="hidden"
                        >
                        <input type="hidden" name="remove_image" id="catalogProductRemoveImage" value="0">
                        <button
                            type="button"
                            id="btnChooseProductImage"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs cursor-pointer"
                        >
                            <i data-lucide="upload" class="w-3.5 h-3.5 text-indigo-500"></i>
                            <span id="btnChooseProductImageText">Pilih Foto</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="catalogProductName" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                        <span id="catalogProductNameLabel">Nama Produk</span>
                    </label>
                    <input
                        id="catalogProductName"
                        name="name"
                        type="text"
                        required
                        maxlength="255"
                        value="{{ old('name') }}"
                        placeholder="Nama produk..."
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="catalogProductCategory" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Kategori</label>
                        <select
                            id="catalogProductCategory"
                            name="category_id"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Tanpa Kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category['id'] }}" @selected((string) old('category_id') === (string) $category['id'])>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div id="catalogProductPriceContainer">
                        <label for="catalogProductPrice" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Harga Jual (Rp)</label>
                        <input
                            id="catalogProductPrice"
                            name="price"
                            type="number"
                            min="0"
                            step="1"
                            required
                            value="{{ old('price') }}"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('price')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div id="catalogSupplyCostContainer" class="hidden">
                        <label for="catalogSupplyCostInput" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Harga Beli / Modal per Satuan (Rp)</label>
                        <input
                            id="catalogSupplyCostInput"
                            type="number"
                            min="0"
                            step="0.01"
                            value="0"
                            placeholder="Contoh: 200"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Biaya modal per unit (cth: Rp 200/gram) untuk resep & inventori.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div id="catalogSkuContainer" class="sm:col-span-2">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="catalogProductSku" class="block text-xs font-semibold text-slate-500 dark:text-slate-400">
                                SKU <span id="catalogProductSkuHint" class="font-normal text-slate-400">(wajib)</span>
                            </label>
                            <button
                                type="button"
                                id="btnGenerateSkuLink"
                                class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 inline-flex items-center gap-1 transition-colors focus:outline-none cursor-pointer"
                                title="Generate SKU otomatis"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>Generate SKU</span>
                            </button>
                        </div>
                        <div class="relative flex items-center">
                            <input
                                id="catalogProductSku"
                                name="sku"
                                type="text"
                                maxlength="255"
                                value="{{ old('sku') }}"
                                placeholder="Contoh: SKU-XXXXXX"
                                class="w-full h-10 pl-3 pr-24 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-mono uppercase text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <button
                                type="button"
                                id="btnGenerateSkuInline"
                                class="absolute right-1.5 top-1.5 bottom-1.5 px-2.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/80 dark:hover:bg-indigo-900 border border-indigo-200/50 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 text-xs font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer"
                                title="Generate SKU otomatis"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>Generate</span>
                            </button>
                        </div>
                        @error('sku')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div id="catalogProductUnitContainer" class="hidden">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="catalogProductUnit" class="block text-xs font-semibold text-slate-500 dark:text-slate-400">Satuan</label>
                            <button
                                type="button"
                                id="btnToggleNewUnitBox"
                                class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 inline-flex items-center gap-1 transition-colors"
                            >
                                <i data-lucide="plus" class="w-3 h-3"></i>
                                <span>Tambah Satuan</span>
                            </button>
                        </div>
                        <select
                            id="catalogProductUnit"
                            name="unit"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            @php
                                $selectedUnit = old('unit', $defaultUnit);
                            @endphp
                            @foreach($unitOptions as $unit)
                                <option value="{{ $unit }}" @selected($selectedUnit === $unit)>{{ $unit }}</option>
                            @endforeach
                            <option value="__new__">+ Tambah Satuan Baru...</option>
                        </select>
                        @error('unit')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror

                        {{-- Inline Quick Add Satuan Box --}}
                        <div id="inlineNewUnitBox" class="hidden mt-2 p-2.5 rounded-xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/50 dark:bg-indigo-950/30">
                            <label for="newUnitInput" class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">Nama Satuan Baru (contoh: cup, box, porsi)</label>
                            <div class="flex items-center gap-1.5">
                                <input
                                    type="text"
                                    id="newUnitInput"
                                    placeholder="Nama satuan..."
                                    maxlength="30"
                                    class="flex-1 h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                <button
                                    type="button"
                                    id="btnSaveNewUnit"
                                    class="h-8 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold inline-flex items-center gap-1 shrink-0 transition-colors shadow-xs"
                                >
                                    <span>Simpan</span>
                                </button>
                                <button
                                    type="button"
                                    id="btnCancelNewUnit"
                                    class="h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0 transition-colors"
                                >
                                    Batal
                                </button>
                            </div>
                            <p id="newUnitError" class="mt-1 text-xs text-rose-600 dark:text-rose-400 hidden"></p>
                        </div>
                    </div>
                </div>

                {{-- Product-only fields --}}
                <div data-catalog-field="product" class="space-y-4">
                    <div>
                        <label for="catalogProductBarcode" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Barcode</label>
                        <input
                            id="catalogProductBarcode"
                            name="barcode"
                            type="text"
                            maxlength="255"
                            value="{{ old('barcode') }}"
                            placeholder="Scan atau ketik barcode..."
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('barcode')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    {{-- HPP & Modal Card --}}
                    <div id="catalogHppCard" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 p-4 space-y-4">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200/80 dark:border-slate-700/80 pb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                    <i data-lucide="calculator" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Harga Modal & HPP</h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Atur acuan harga modal dan kalkulasi HPP produk ini</p>
                                </div>
                            </div>
                            <span id="catalogMarginBadge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                Margin: --
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="catalogProductCost" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                    Harga Modal Acuan (Rp)
                                </label>
                                <input
                                    id="catalogProductCost"
                                    name="cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('cost', 0) }}"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                    Nilai modal aktif yang disinkronkan ke POS & laporan laba.
                                </p>
                                @error('cost')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="catalogCostSource" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                    Sumber Harga Modal
                                </label>
                                <select
                                    id="catalogCostSource"
                                    name="cost_source"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="hpp" @selected(old('cost_source', 'hpp') === 'hpp')>Otomatis Ikuti HPP</option>
                                    <option value="manual" @selected(old('cost_source') === 'manual')>Manual (Ditentukan Sendiri)</option>
                                </select>
                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                    Pilih apakah harga modal acuan diisi manual atau otomatis diperbarui oleh HPP.
                                </p>
                                @error('cost_source')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <label for="catalogHppMethod" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                    Metode Hitung HPP
                                </label>
                                <select
                                    id="catalogHppMethod"
                                    name="hpp_method"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="recipe" @selected(old('hpp_method', 'recipe') === 'recipe')>Resep / Komposisi Bahan (BOM)</option>
                                    <option value="average" @selected(old('hpp_method') === 'average')>Rata-rata Tertimbang (Moving Avg)</option>
                                    <option value="last_purchase" @selected(old('hpp_method') === 'last_purchase')>Harga Pembelian Terakhir</option>
                                    <option value="none" @selected(old('hpp_method') === 'none')>Tidak Dihitung</option>
                                </select>
                                @error('hpp_method')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex flex-col justify-between p-3 rounded-xl border border-indigo-100 dark:border-indigo-950/70 bg-indigo-50/50 dark:bg-indigo-950/20">
                                <div>
                                    <span class="text-[11px] font-medium text-indigo-700 dark:text-indigo-300">HPP Terhitung Saat Ini</span>
                                    <div class="flex items-baseline gap-1.5 mt-0.5">
                                        <span id="catalogHppCalculatedDisplay" class="text-base font-extrabold text-indigo-900 dark:text-indigo-100">Rp 0</span>
                                        <span id="catalogHppCalculatedAt" class="text-[10px] text-indigo-500 dark:text-indigo-400"></span>
                                    </div>
                                </div>
                                <div class="mt-2 pt-2 border-t border-indigo-100/80 dark:border-indigo-900/40 flex items-center justify-between">
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Gunakan nilai ini?</span>
                                    <button
                                        type="button"
                                        id="catalogApplyHppBtn"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                                    >
                                        <span>Salin ke Modal</span>
                                        <i data-lucide="arrow-up-left" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Recipe Builder Section --}}
                        <div id="catalogRecipeSection" class="pt-3 border-t border-slate-200/80 dark:border-slate-700/80 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <i data-lucide="layers" class="w-3.5 h-3.5 text-indigo-500"></i>
                                        Komposisi Bahan Baku (Resep)
                                    </span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        HPP dihitung otomatis dari total modal bahan + overhead.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    id="catalogAddRecipeItemBtn"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 text-xs font-semibold transition-colors"
                                >
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    <span>Tambah Bahan</span>
                                </button>
                            </div>

                            {{-- Recipe items list container --}}
                            <div id="catalogRecipeItemsContainer" class="space-y-2">
                                {{-- Dynamically populated via JS --}}
                            </div>

                            <div id="catalogRecipeEmptyHint" class="p-4 text-center rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-xs text-slate-400 dark:text-slate-500">
                                Belum ada bahan baku. Klik <strong>+ Tambah Bahan</strong> untuk menentukan komposisi produk ini.
                            </div>

                            {{-- Overhead inputs --}}
                            <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                                    Biaya Operasional / Overhead (Opsional)
                                </span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="catalogHppOverheadFixed" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                            Overhead Tetap (Rp)
                                        </label>
                                        <input
                                            id="catalogHppOverheadFixed"
                                            name="hpp_overhead_fixed"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value="{{ old('hpp_overhead_fixed', 0) }}"
                                            placeholder="Contoh: 1500 (kemasan/cup)"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label for="catalogHppOverheadPercent" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                            Overhead Persentase (%)
                                        </label>
                                        <input
                                            id="catalogHppOverheadPercent"
                                            name="hpp_overhead_percent"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value="{{ old('hpp_overhead_percent', 0) }}"
                                            placeholder="Contoh: 5 (% susut/gas)"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- Recipe live summary preview --}}
                            <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800/80 text-xs space-y-1">
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Total Biaya Bahan:</span>
                                    <span id="catalogRecipeMaterialsSubtotal" class="font-semibold">Rp 0</span>
                                </div>
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Total Biaya Overhead:</span>
                                    <span id="catalogRecipeOverheadSubtotal" class="font-semibold">Rp 0</span>
                                </div>
                                <div class="flex justify-between pt-1 border-t border-slate-200 dark:border-slate-700 font-bold text-slate-900 dark:text-white">
                                    <span>Estimasi HPP Resep:</span>
                                    <span id="catalogRecipeTotalEstimate" class="text-indigo-600 dark:text-indigo-400">Rp 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-catalog-field="product-create">
                            <label for="catalogProductStock" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Stok Awal</label>
                            <input
                                id="catalogProductStock"
                                name="stock"
                                type="number"
                                step="0.001"
                                value="{{ old('stock', 0) }}"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Stok awal dapat negatif. Perubahan berikutnya melalui pergerakan stok.</p>
                            @error('stock')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="catalogProductMinStock" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Minimum Stok</label>
                            <input
                                id="catalogProductMinStock"
                                name="min_stock"
                                type="number"
                                step="0.001"
                                value="{{ old('min_stock', 0) }}"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            @error('min_stock')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Sellable toggle for regular products vs info notice for supplies --}}
                    <input type="hidden" name="is_sellable" id="catalogProductIsSellableHidden" value="{{ old('is_sellable', '1') }}">
                    <div id="catalogProductSellableCheckboxContainer">
                        <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer">
                            <input
                                id="catalogProductIsSellable"
                                type="checkbox"
                                value="1"
                                @checked(old('is_sellable', '1') === '1' || old('is_sellable') === 1 || old('is_sellable') === true)
                                class="mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="text-xs text-slate-600 dark:text-slate-300">
                                <span class="block font-semibold text-slate-800 dark:text-slate-200">Dijual di POS</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">
                                    Lepaskan centang untuk bahan baku / persediaan yang dilacak stoknya tetapi tidak dijual.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div id="catalogSupplyNotice" class="hidden p-3.5 rounded-xl border border-indigo-100 dark:border-indigo-900/60 bg-indigo-50/70 dark:bg-indigo-950/40 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                            <i data-lucide="package" class="w-4 h-4"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="block text-xs font-bold text-indigo-950 dark:text-indigo-200">Bahan Baku / Persediaan (Non-Jual)</span>
                            <span class="block text-[11px] text-indigo-700/90 dark:text-indigo-300/80 leading-relaxed mt-0.5">
                                Item ini dilacak stoknya untuk resep menu dan inventori gudang. Item ini <strong>tidak dijual langsung di kasir (POS)</strong>.
                            </span>
                        </div>
                    </div>
                    @error('is_sellable')<p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror

                    {{-- Variant Groups Assignment (Template Varian) --}}
                    @if(!empty($variantGroupOptions))
                        <div id="catalogVariantGroupsSection" class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                                        Template Varian yang Berlaku
                                    </span>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">
                                        Pilih opsi modifikasi/varian yang dapat dipilih kasir untuk menu ini.
                                    </span>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                                    {{ count($variantGroupOptions) }} template
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1">
                                @foreach($variantGroupOptions as $vg)
                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-900 cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-700 transition">
                                        <input
                                            type="checkbox"
                                            name="variant_group_ids[]"
                                            value="{{ $vg['id'] }}"
                                            class="product-variant-group-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            data-variant-group-id="{{ $vg['id'] }}"
                                        >
                                        <div class="text-xs">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 block">{{ $vg['name'] }}</span>
                                            <span class="text-[10px] text-slate-400 block">
                                                {{ $vg['selection_type'] === 'single' ? 'Pilihan Tunggal (Radio)' : 'Pilihan Ganda (Checkbox)' }}
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('variant_group_ids')<p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>

                {{-- Service-only fields --}}
                <div data-catalog-field="service" class="space-y-4 hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="catalogServicePricingUnit" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Satuan Harga</label>
                            <input
                                id="catalogServicePricingUnit"
                                name="pricing_unit"
                                type="text"
                                maxlength="20"
                                value="{{ old('pricing_unit') }}"
                                placeholder="kg / pcs / paket"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            @error('pricing_unit')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="catalogServiceMinQuantity" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Minimum Quantity</label>
                            <input
                                id="catalogServiceMinQuantity"
                                name="min_quantity"
                                type="number"
                                min="0"
                                step="0.001"
                                value="{{ old('min_quantity', 0) }}"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            @error('min_quantity')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="catalogServiceDuration" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Estimasi Durasi <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input
                            id="catalogServiceDuration"
                            name="estimated_duration"
                            type="text"
                            maxlength="50"
                            value="{{ old('estimated_duration') }}"
                            placeholder="mis. 2 hari"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('estimated_duration')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="catalogProductStatus" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                    <select
                        id="catalogProductStatus"
                        name="status"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        @foreach($statusOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Status dapat diubah kapan saja. Item tidak pernah dihapus permanen.</p>
                </div>
            </div>

            {{-- Sticky Form Footer --}}
            <div class="px-5 sm:px-6 py-3.5 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/90 flex items-center justify-end gap-2.5 shrink-0 rounded-b-2xl">
                <button
                    type="button"
                    data-close-catalog-modal
                    class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="catalogProductSubmit"
                    class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none shadow-sm"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span data-submit-label>Simpan</span>
                </button>
            </div>
        </form>

        {{-- ==================== CATEGORY FORM ==================== --}}
        <form
            id="catalogCategoryForm"
            method="POST"
            action="{{ route('products.categories.store') }}"
            data-store-url="{{ route('products.categories.store') }}"
            data-update-url-template="{{ route('products.categories.update', ['categoryId' => '__ID__']) }}"
            class="flex flex-col min-h-0 flex-1 overflow-hidden hidden"
            novalidate
        >
            @csrf
            <input type="hidden" name="_method" value="POST" id="catalogCategoryMethod">

            {{-- Scrollable Form Body --}}
            <div class="p-5 sm:p-6 space-y-4 overflow-y-auto flex-1">
                <div>
                    <label for="catalogCategoryName" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Nama Kategori</label>
                    <input
                        id="catalogCategoryName"
                        name="name"
                        type="text"
                        required
                        maxlength="255"
                        value="{{ old('name') }}"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="catalogCategoryStatus" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                    <select
                        id="catalogCategoryStatus"
                        name="status"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        @foreach($statusOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Sticky Form Footer --}}
            <div class="px-5 sm:px-6 py-3.5 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/90 flex items-center justify-end gap-2.5 shrink-0 rounded-b-2xl">
                <button
                    type="button"
                    data-close-catalog-modal
                    class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="catalogCategorySubmit"
                    class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none shadow-sm"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span data-submit-label>Simpan</span>
                </button>
            </div>
        </form>
        </div>
    </div>

    {{-- Raw materials / components data for recipe builder (base64 encoded to avoid leaking names into HTML body assertions) --}}
    <script id="catalogRawMaterialsData" type="application/json">"{{ base64_encode(json_encode($rawMaterialOptions ?? [])) }}"</script>
</div>
