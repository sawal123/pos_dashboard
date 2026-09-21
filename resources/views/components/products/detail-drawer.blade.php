{{-- ==================== PRODUCT / SERVICE DETAIL DRAWER ==================== --}}
<div
    id="productDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="productDrawerTitle"
>
    {{-- Backdrop --}}
    <div
        id="productDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="productDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div>
                <span id="productDrawerSubheading" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Detail Item
                </span>
                <h2 id="productDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                    -
                </h2>
            </div>
            <button
                type="button"
                id="closeProductDrawerBtn"
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail item"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Drawer Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs sm:text-sm">

            {{-- 1. Status & Basic Badges Grid --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                <div class="flex items-center justify-between gap-2 flex-wrap pb-3 border-b border-slate-200/60 dark:border-slate-700/60">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Jenis Katalog</span>
                        <span id="detailItemKind" class="font-bold text-indigo-600 dark:text-indigo-400">Produk</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="detailItemStatusBadge" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold">
                            -
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">SKU</span>
                        <span id="detailItemSku" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div id="detailBarcodeContainer">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Barcode</span>
                        <span id="detailItemBarcode" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Kategori</span>
                        <span id="detailItemCategory" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                </div>
            </div>

            {{-- 2. Pricing & Financial Section --}}
            <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3 text-xs">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Informasi Harga
                </h3>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Harga Jual</span>
                    <span id="detailItemPrice" class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white tabular-nums">Rp 0</span>
                </div>
                <div id="detailItemCostRow" class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">HPP (Harga Pokok)</span>
                    <span id="detailItemCost" class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">Rp 0</span>
                </div>
                <div id="detailPricingUnitRow" class="hidden flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Satuan Penetapan Harga</span>
                    <span id="detailPricingUnit" class="font-semibold text-slate-700 dark:text-slate-300">-</span>
                </div>
            </div>

            {{-- 3. Product Specific Inventory Section --}}
            <div id="detailProductInventorySection" class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                        Status Inventori
                    </h3>
                    <span id="detailStockBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold">
                        -
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Stok Saat Ini</span>
                        <span id="detailItemStock" class="font-extrabold text-base text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Minimum Stok</span>
                        <span id="detailItemMinStock" class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">-</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Satuan Barang</span>
                        <span id="detailItemUnit" class="font-semibold text-slate-700 dark:text-slate-300">-</span>
                    </div>
                </div>
            </div>

            {{-- 4. Service Specific Operational Section --}}
            <div id="detailServiceOperationalSection" class="hidden p-4 rounded-2xl bg-sky-50/60 dark:bg-sky-950/40 border border-sky-200/70 dark:border-sky-800/60 space-y-3 text-xs">
                <div class="flex items-center gap-1.5 text-sky-700 dark:text-sky-300 font-bold">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Ketentuan Operasional Layanan</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Minimum Order</span>
                        <span id="detailServiceMinQty" class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Estimasi Durasi</span>
                        <span id="detailServiceDuration" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- Drawer Footer: Action Buttons --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center gap-2.5 bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                id="drawerEditCatalogBtn"
                onclick="showToast('info', 'Fitur pengelolaan akan tersedia setelah integrasi data.')"
                class="flex-1 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                <i data-lucide="pencil" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i>
                <span>Edit Item</span>
            </button>
            <button
                type="button"
                id="closeProductDrawerFooterBtn"
                class="py-2.5 px-4 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
