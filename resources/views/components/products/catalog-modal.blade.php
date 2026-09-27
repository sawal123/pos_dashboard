{{-- DASH-15 — owner-only catalog modal (create/edit product, service, category). --}}
@props([
    'categories' => [],
    'businessType' => null,
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
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-catalog-modal></div>

    <div class="fixed inset-0 flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-lg my-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 sticky top-0 bg-white dark:bg-slate-900 z-10 rounded-t-2xl">
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
                class="p-5 space-y-4"
                novalidate
            >
                @csrf
                <input type="hidden" name="_method" value="POST" id="catalogProductMethod">
                <input type="hidden" name="kind" value="product" id="catalogProductKind">

                <div>
                    <label for="catalogProductName" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Nama</label>
                    <input
                        id="catalogProductName"
                        name="name"
                        type="text"
                        required
                        maxlength="255"
                        value="{{ old('name') }}"
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

                    <div>
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
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="catalogProductSku" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                            SKU <span id="catalogProductSkuHint" class="font-normal text-slate-400">(wajib)</span>
                        </label>
                        <input
                            id="catalogProductSku"
                            name="sku"
                            type="text"
                            maxlength="255"
                            value="{{ old('sku') }}"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('sku')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="catalogProductUnit" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Satuan</label>
                        <input
                            id="catalogProductUnit"
                            name="unit"
                            type="text"
                            maxlength="20"
                            value="{{ old('unit') }}"
                            placeholder="pcs"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('unit')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Product-only fields --}}
                <div data-catalog-field="product" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="catalogProductBarcode" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Barcode</label>
                            <input
                                id="catalogProductBarcode"
                                name="barcode"
                                type="text"
                                maxlength="255"
                                value="{{ old('barcode') }}"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            @error('barcode')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="catalogProductCost" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">HPP (Rp)</label>
                            <input
                                id="catalogProductCost"
                                name="cost"
                                type="number"
                                min="0"
                                step="0.01"
                                value="{{ old('cost') }}"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            @error('cost')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
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

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                    <button
                        type="button"
                        data-close-catalog-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="catalogProductSubmit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none"
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
                class="p-5 space-y-4 hidden"
                novalidate
            >
                @csrf
                <input type="hidden" name="_method" value="POST" id="catalogCategoryMethod">

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

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                    <button
                        type="button"
                        data-close-catalog-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="catalogCategorySubmit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none"
                    >
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span data-submit-label>Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
