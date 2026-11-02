<x-layouts::app :title="'Produk & Layanan'">
    <main id="mainContent" data-products-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== PRODUCTS HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Produk & Layanan
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola katalog produk, layanan, harga, dan kategori bisnis.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                    <i data-lucide="package" class="w-3.5 h-3.5"></i>
                    <span>Katalog Bisnis</span>
                </span>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Perubahan tidak dapat disimpan.</p>
                </div>
                <ul class="pl-8 list-disc text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-products.summary-cards :summary="$summary" />

        {{-- ==================== 2. TABS NAVIGATOR ==================== --}}
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800" role="tablist" aria-label="Navigasi Katalog">
            <a
                href="{{ route('products.index', ['tab' => 'products']) }}"
                wire:navigate
                id="tabProducts"
                role="tab"
                aria-selected="{{ $activeTab === 'products' ? 'true' : 'false' }}"
                aria-controls="panelProducts"
                tabindex="{{ $activeTab === 'products' ? '0' : '-1' }}"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 {{ $activeTab === 'products' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }} transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
            >
                <i data-lucide="package" class="w-4 h-4"></i>
                <span>Produk</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'products' ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} tabular-nums">
                    {{ $tabCounts['products'] ?? 0 }}
                </span>
            </a>
            <a
                href="{{ route('products.index', ['tab' => 'supplies']) }}"
                wire:navigate
                id="tabSupplies"
                role="tab"
                aria-selected="{{ $activeTab === 'supplies' ? 'true' : 'false' }}"
                aria-controls="panelSupplies"
                tabindex="{{ $activeTab === 'supplies' ? '0' : '-1' }}"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 {{ $activeTab === 'supplies' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }} transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
            >
                <i data-lucide="warehouse" class="w-4 h-4"></i>
                <span>Persediaan</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'supplies' ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} tabular-nums">
                    {{ $tabCounts['supplies'] ?? 0 }}
                </span>
            </a>
            <a
                href="{{ route('products.index', ['tab' => 'services']) }}"
                wire:navigate
                id="tabServices"
                role="tab"
                aria-selected="{{ $activeTab === 'services' ? 'true' : 'false' }}"
                aria-controls="panelServices"
                tabindex="{{ $activeTab === 'services' ? '0' : '-1' }}"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 {{ $activeTab === 'services' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }} transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
            >
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                <span>Layanan</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'services' ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} tabular-nums">
                    {{ $tabCounts['services'] ?? 0 }}
                </span>
            </a>
            <a
                href="{{ route('products.index', ['tab' => 'categories']) }}"
                wire:navigate
                id="tabCategories"
                role="tab"
                aria-selected="{{ $activeTab === 'categories' ? 'true' : 'false' }}"
                aria-controls="panelCategories"
                tabindex="{{ $activeTab === 'categories' ? '0' : '-1' }}"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 {{ $activeTab === 'categories' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }} transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
            >
                <i data-lucide="tags" class="w-4 h-4"></i>
                <span>Kategori</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'categories' ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} tabular-nums">
                    {{ $tabCounts['categories'] ?? 0 }}
                </span>
            </a>
        </div>

        {{-- ==================== 3. FILTER BAR ==================== --}}
        <x-products.filter-bar
            :categories="$categories"
            :statuses="$statuses"
            :active-tab="$activeTab"
            :current-filters="$filters"
            :can-manage="$canManageCatalog"
        />

        {{-- ==================== 4. DATA LIST / EMPTY STATE ==================== --}}
        @if($items->total() > 0)
            @if($activeTab === 'products')
                <div id="panelProducts" role="tabpanel" aria-labelledby="tabProducts" class="catalog-panel space-y-4">
                    <x-products.product-table :products="$items" :can-manage="$canManageCatalog" />
                </div>
            @elseif($activeTab === 'supplies')
                <div id="panelSupplies" role="tabpanel" aria-labelledby="tabSupplies" class="catalog-panel space-y-4">
                    <x-products.product-table :products="$items" :can-manage="$canManageCatalog" />
                </div>
            @elseif($activeTab === 'services')
                <div id="panelServices" role="tabpanel" aria-labelledby="tabServices" class="catalog-panel space-y-4">
                    <x-products.service-table :services="$items" :can-manage="$canManageCatalog" />
                </div>
            @elseif($activeTab === 'categories')
                <div id="panelCategories" role="tabpanel" aria-labelledby="tabCategories" class="catalog-panel space-y-4">
                    <x-products.category-table :categories="$items" :can-manage="$canManageCatalog" />
                </div>
            @endif

            {{-- Mobile Cards (Unified Container) --}}
            <div id="mobileCardsWrapper">
                <x-products.mobile-cards
                    :items="$items"
                    :active-tab="$activeTab === 'supplies' ? 'products' : $activeTab"
                    :can-manage="$canManageCatalog"
                />
            </div>

            {{-- ==================== 5. PAGINATION ==================== --}}
            <div id="catalogPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->total() }}</span>
                    item
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Katalog">
                    {{-- Previous --}}
                    @if($items->onFirstPage())
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs">
                            Sebelumnya
                        </span>
                    @else
                        <a href="{{ $items->previousPageUrl() }}"
                           class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            Sebelumnya
                        </a>
                    @endif

                    {{-- Page numbers (up to 7 windows) --}}
                    @foreach($items->getUrlRange(max(1, $items->currentPage() - 3), min($items->lastPage(), $items->currentPage() + 3)) as $page => $url)
                        @if($page === $items->currentPage())
                            <span
                                class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center"
                                aria-current="page"
                            >{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs flex items-center justify-center hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}"
                           class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            Berikutnya
                        </a>
                    @else
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs">
                            Berikutnya
                        </span>
                    @endif
                </nav>
            </div>
        @else
            {{-- Empty State (Filtered or Truly Empty) --}}
            <x-products.empty-state
                :mode="($hasAnyData ?? false) ? 'no-results' : 'no-data'"
                :active-tab="$activeTab"
            />
        @endif

        {{-- ==================== 6. DETAIL DRAWER ==================== --}}
        <x-products.detail-drawer :can-manage="$canManageCatalog" />

        @if($canManageCatalog)
            {{-- ==================== 7. CATALOG MODAL (DASH-15) ==================== --}}
            <x-products.catalog-modal
                :categories="$categories"
                :business-type="$dashboardBusinessType ?? null"
                :raw-material-options="$rawMaterialOptions ?? []"
            />
        @endif

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initProductsPage() {
            const root = document.querySelector('main[data-products-page="true"]');
            if (!root || root.dataset.productsInitialized === 'true') {
                return;
            }
            root.dataset.productsInitialized = 'true';

            // Drawer Elements
            const drawerWrapper = document.getElementById('productDrawerWrapper');
            const drawerBackdrop = document.getElementById('productDrawerBackdrop');
            const drawerPanel = document.getElementById('productDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeProductDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeProductDrawerFooterBtn');
            const drawerEditCatalogBtn = document.getElementById('drawerEditCatalogBtn');

            let lastTriggerElement = null;
            let currentItemData = null;

            function formatRupiah(num) {
                const val = Number(num || 0);
                const hasFraction = Math.abs(val % 1) > 0.0001;
                const formatted = new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: hasFraction ? 2 : 0,
                    maximumFractionDigits: 2,
                }).format(val);
                return 'Rp ' + formatted;
            }

            // Basic Focus Trap for Detail Drawer
            function handleProductDrawerTrap(e) {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDrawer();
                    return;
                }

                if (e.key === 'Tab') {
                    const focusables = drawerPanel.querySelectorAll(
                        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    if (focusables.length === 0) return;

                    const firstEl = focusables[0];
                    const lastEl = focusables[focusables.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            lastEl.focus();
                        }
                    } else {
                        if (document.activeElement === lastEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            firstEl.focus();
                        }
                    }
                }
            }

            // Safe DOM Rendering for Drawer
            function openDrawer(itemData) {
                if (!drawerWrapper || !itemData) return;
                currentItemData = itemData;

                const isService = itemData.kind === 'service';

                // Hand the row data to the DASH-15 edit modal through the drawer
                // button so the two features share one source without coupling.
                if (drawerEditCatalogBtn) {
                    drawerEditCatalogBtn.dataset.catalogKind = isService ? 'service' : 'product';
                    drawerEditCatalogBtn.dataset.catalogRaw = JSON.stringify(itemData);
                }

                // Subheading & Title
                document.getElementById('productDrawerSubheading').textContent = isService ? 'Detail Layanan' : 'Detail Produk';
                document.getElementById('productDrawerTitle').textContent = itemData.name || '-';
                document.getElementById('detailItemKind').textContent = isService ? 'Layanan' : 'Produk';

                // Status Badge (DOM Safe, non-deleted items)
                const statusBadge = document.getElementById('detailItemStatusBadge');
                statusBadge.textContent = '';
                const statusDot = document.createElement('span');
                const statusText = document.createElement('span');

                const rawStatus = itemData.status_raw || itemData.status;
                if (rawStatus === 'active') {
                    statusBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    statusDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    statusText.textContent = 'Aktif';
                } else if (rawStatus === 'inactive') {
                    statusBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                    statusDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    statusText.textContent = 'Nonaktif';
                } else {
                    statusBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    statusDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    statusText.textContent = itemData.status || rawStatus || '-';
                }
                statusBadge.appendChild(statusDot);
                statusBadge.appendChild(statusText);

                // SKU, Barcode, Kategori
                document.getElementById('detailItemSku').textContent = itemData.sku || '-';

                const barcodeContainer = document.getElementById('detailBarcodeContainer');
                if (!isService && itemData.barcode) {
                    barcodeContainer.classList.remove('hidden');
                    document.getElementById('detailItemBarcode').textContent = itemData.barcode;
                } else {
                    barcodeContainer.classList.add('hidden');
                }
                document.getElementById('detailItemCategory').textContent = itemData.category_name || '-';

                // Price Section
                document.getElementById('detailItemPrice').textContent = formatRupiah(itemData.price);

                const costRow = document.getElementById('detailItemCostRow');
                const pricingUnitRow = document.getElementById('detailPricingUnitRow');
                const productInventorySec = document.getElementById('detailProductInventorySection');
                const serviceOperationalSec = document.getElementById('detailServiceOperationalSection');

                const detailRecipeSec = document.getElementById('detailItemRecipeSection');

                if (isService) {
                    costRow.classList.add('hidden');
                    if (detailRecipeSec) detailRecipeSec.classList.add('hidden');
                    pricingUnitRow.classList.remove('hidden');
                    const unitStr = itemData.pricing_unit || itemData.unit || '';
                    document.getElementById('detailPricingUnit').textContent = unitStr ? `Per ${unitStr}` : '-';

                    productInventorySec.classList.add('hidden');
                    serviceOperationalSec.classList.remove('hidden');

                    let minQtyText = '-';
                    if (itemData.min_quantity !== null && itemData.min_quantity !== undefined && itemData.min_quantity !== '') {
                        const unitPart = itemData.unit ? ` ${itemData.unit}` : '';
                        minQtyText = `${itemData.min_quantity}${unitPart}`;
                    }
                    document.getElementById('detailServiceMinQty').textContent = minQtyText;
                    document.getElementById('detailServiceDuration').textContent = itemData.estimated_duration || '-';
                } else {
                    costRow.classList.remove('hidden');
                    document.getElementById('detailItemCost').textContent = formatRupiah(itemData.cost);

                    // Cost Source Badge
                    const costSourceBadge = document.getElementById('detailCostSourceBadge');
                    if (costSourceBadge) {
                        const isAutoHpp = itemData.cost_source === 'hpp';
                        costSourceBadge.textContent = isAutoHpp ? 'Otomatis HPP' : 'Manual';
                        costSourceBadge.className = isAutoHpp
                            ? 'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                            : 'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
                    }

                    // HPP Row & Method Badge
                    const hppEl = document.getElementById('detailItemHpp');
                    const hppMethodBadge = document.getElementById('detailHppMethodBadge');
                    if (hppEl) {
                        hppEl.textContent = formatRupiah(itemData.hpp ?? 0);
                    }
                    if (hppMethodBadge) {
                        const methodLabels = {
                            'average': 'Rata-rata',
                            'last_purchase': 'Beli Terakhir',
                            'recipe': 'Resep (BOM)',
                            'none': 'None'
                        };
                        hppMethodBadge.textContent = methodLabels[itemData.hpp_method] || 'Rata-rata';
                    }

                    // Margin Row
                    const marginEl = document.getElementById('detailItemMargin');
                    if (marginEl) {
                        if (itemData.margin_percent !== undefined && itemData.margin_percent !== null) {
                            const pct = Number(itemData.margin_percent).toFixed(1);
                            const amt = formatRupiah(itemData.margin_amount ?? 0);
                            marginEl.textContent = `${amt} (${pct}%)`;
                            marginEl.className = Number(itemData.margin_percent) >= 0
                                ? 'font-bold tabular-nums text-emerald-600 dark:text-emerald-400'
                                : 'font-bold tabular-nums text-rose-600 dark:text-rose-400';
                        } else {
                            marginEl.textContent = '-';
                            marginEl.className = 'font-bold tabular-nums text-slate-500';
                        }
                    }

                    // Recipe Composition Section
                    const recipeItemsList = document.getElementById('detailRecipeItemsList');
                    const recipeCount = document.getElementById('detailRecipeItemCount');
                    const recipeOverheadText = document.getElementById('detailRecipeOverheadText');

                    if (detailRecipeSec && recipeItemsList) {
                        const items = itemData.recipe_items || [];
                        if (items.length > 0) {
                            detailRecipeSec.classList.remove('hidden');
                            if (recipeCount) recipeCount.textContent = `${items.length} bahan`;
                            recipeItemsList.innerHTML = items.map(ri => {
                                const subtotal = (Number(ri.quantity) * Number(ri.component_cost || 0));
                                return `
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-[11px]">
                                        <div class="min-w-0 pr-2">
                                            <span class="font-bold text-slate-800 dark:text-slate-200 block truncate">${ri.component_name || 'Bahan'}</span>
                                            <span class="text-slate-400 dark:text-slate-500">${ri.quantity} ${ri.component_unit || 'pcs'} @ ${formatRupiah(ri.component_cost || 0)}</span>
                                        </div>
                                        <span class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums shrink-0">${formatRupiah(subtotal)}</span>
                                    </div>
                                `;
                            }).join('');

                            if (recipeOverheadText) {
                                const fixed = Number(itemData.hpp_overhead_fixed || 0);
                                const pct = Number(itemData.hpp_overhead_percent || 0);
                                const parts = [];
                                if (fixed > 0) parts.push(formatRupiah(fixed));
                                if (pct > 0) parts.push(`${pct}%`);
                                recipeOverheadText.textContent = parts.length > 0 ? parts.join(' + ') : 'Rp 0';
                            }
                        } else {
                            detailRecipeSec.classList.add('hidden');
                        }
                    }

                    pricingUnitRow.classList.add('hidden');

                    productInventorySec.classList.remove('hidden');
                    serviceOperationalSec.classList.add('hidden');

                    const stockNum = parseFloat(itemData.stock || 0);
                    const minStockNum = parseFloat(itemData.min_stock || 0);
                    const unit = itemData.unit || 'pcs';

                    document.getElementById('detailItemStock').textContent = `${itemData.stock} ${unit}`;
                    document.getElementById('detailItemMinStock').textContent = `${itemData.min_stock} ${unit}`;
                    document.getElementById('detailItemUnit').textContent = unit;

                    // Deterministic stock status badge
                    const stockBadge = document.getElementById('detailStockBadge');
                    stockBadge.textContent = '';
                    const stockStatus = itemData.stock_status || (stockNum < 0 ? 'negative' : (stockNum === 0 ? 'empty' : (stockNum <= minStockNum ? 'low' : 'safe')));

                    if (stockStatus === 'negative') {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-950/80 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300';
                        stockBadge.textContent = 'Minus';
                    } else if (stockStatus === 'empty') {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                        stockBadge.textContent = 'Habis';
                    } else if (stockStatus === 'low') {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/70 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                        stockBadge.textContent = 'Menipis';
                    } else {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                        stockBadge.textContent = 'Aman';
                    }
                }

                // Show Drawer with Smooth Animation & Focus Management
                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });

                document.addEventListener('keydown', handleProductDrawerTrap);

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleProductDrawerTrap);

                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTriggerElement && typeof lastTriggerElement.focus === 'function') {
                        lastTriggerElement.focus();
                    }
                }, 300);
            }

            // Event Delegation for Opening Product / Service Drawer
            document.querySelectorAll('.view-product-detail-btn, .view-service-detail-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.product-row, .product-card, .service-row, .service-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                const data = JSON.parse(raw);
                                openDrawer(data);
                            } catch (e) {
                                console.error('Failed to parse item data', e);
                            }
                        }
                    }
                };
            });

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;
        }

        initProductsPage();

        if (!window.__productsListenersBound) {
            window.__productsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initProductsPage);
            document.addEventListener('livewire:navigated', initProductsPage);
        }
    </script>

    @if($canManageCatalog)
        @if($errors->any())
            <script>
                window.__catalogModalOpenOnError = @json(old('kind', $activeTab === 'services' ? 'service' : ($activeTab === 'categories' ? 'category' : 'product')));
            </script>
        @endif

        {{-- DASH-15 — catalog modal: open/close, kind switching, edit prefill. --}}
        <script>
            (function () {
                if (window.__catalogModalBound) {
                    return;
                }
                window.__catalogModalBound = true;

                const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

                let lastTrigger = null;
                let currentEditingProductId = null;
                let currentHppCalculated = 0;

                const modalEl = () => document.getElementById('catalogModal');
                const productForm = () => document.getElementById('catalogProductForm');
                const categoryForm = () => document.getElementById('catalogCategoryForm');
                const recipeContainer = () => document.getElementById('catalogRecipeItemsContainer');
                const recipeSection = () => document.getElementById('catalogRecipeSection');
                const emptyHint = () => document.getElementById('catalogRecipeEmptyHint');

                const getRawMaterials = () => {
                    const scriptEl = document.getElementById('catalogRawMaterialsData');
                    if (!scriptEl) return [];
                    try {
                        let text = scriptEl.textContent ? scriptEl.textContent.trim() : '';
                        if (text.startsWith('"') && text.endsWith('"')) {
                            text = text.slice(1, -1);
                        }
                        const decoded = text ? atob(text) : '[]';
                        const all = JSON.parse(decoded || '[]');
                        if (currentEditingProductId) {
                            return all.filter(m => Number(m.id) !== Number(currentEditingProductId));
                        }
                        return all;
                    } catch (e) {
                        return [];
                    }
                };

                const formatRupiahVal = (val) => {
                    const num = Number(val) || 0;
                    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
                };

                const updateLiveCalculations = () => {
                    const form = productForm();
                    if (!form) return;

                    const priceInput = form.elements.namedItem('price');
                    const costInput = form.elements.namedItem('cost');
                    const costSourceSelect = form.elements.namedItem('cost_source');
                    const hppMethodSelect = form.elements.namedItem('hpp_method');
                    const overheadFixedInput = form.elements.namedItem('hpp_overhead_fixed');
                    const overheadPercentInput = form.elements.namedItem('hpp_overhead_percent');

                    const hppMethod = hppMethodSelect ? hppMethodSelect.value : 'average';
                    const costSource = costSourceSelect ? costSourceSelect.value : 'manual';
                    const sellingPrice = parseFloat(priceInput?.value) || 0;

                    // If recipe method: calculate recipe total
                    let recipeMaterialsSubtotal = 0;
                    const container = recipeContainer();
                    if (container) {
                        const rows = container.querySelectorAll('.recipe-row');
                        rows.forEach(row => {
                            const select = row.querySelector('.recipe-component-select');
                            const qtyInput = row.querySelector('.recipe-qty-input');
                            const subtotalEl = row.querySelector('.recipe-row-subtotal');
                            const unitEl = row.querySelector('.recipe-row-unit');

                            const selectedOption = select?.selectedOptions?.[0];
                            const hasSelection = Boolean(select?.value);
                            const componentCost = hasSelection ? (parseFloat(selectedOption?.dataset?.cost) || 0) : 0;
                            const componentUnit = hasSelection ? (selectedOption?.dataset?.unit || '-') : '-';
                            const qty = parseFloat(qtyInput?.value) || 0;

                            if (unitEl) unitEl.textContent = componentUnit;
                            const rowSubtotal = componentCost * qty;
                            if (subtotalEl) subtotalEl.textContent = formatRupiahVal(rowSubtotal);

                            recipeMaterialsSubtotal += rowSubtotal;
                        });
                    }

                    const overheadFixed = parseFloat(overheadFixedInput?.value) || 0;
                    const overheadPercent = parseFloat(overheadPercentInput?.value) || 0;
                    const overheadFromPercent = (recipeMaterialsSubtotal * overheadPercent) / 100;
                    const totalOverhead = overheadFixed + overheadFromPercent;
                    const totalRecipeHpp = recipeMaterialsSubtotal + totalOverhead;

                    // Update recipe preview elements
                    const matSubtotalEl = document.getElementById('catalogRecipeMaterialsSubtotal');
                    const ovhSubtotalEl = document.getElementById('catalogRecipeOverheadSubtotal');
                    const totalEstEl = document.getElementById('catalogRecipeTotalEstimate');
                    if (matSubtotalEl) matSubtotalEl.textContent = formatRupiahVal(recipeMaterialsSubtotal);
                    if (ovhSubtotalEl) ovhSubtotalEl.textContent = formatRupiahVal(totalOverhead);
                    if (totalEstEl) totalEstEl.textContent = formatRupiahVal(totalRecipeHpp);

                    // Calculated HPP display
                    const hppDisplayEl = document.getElementById('catalogHppCalculatedDisplay');
                    let effectiveHpp = currentHppCalculated;
                    if (hppMethod === 'recipe') {
                        effectiveHpp = totalRecipeHpp;
                    }
                    if (hppDisplayEl) {
                        hppDisplayEl.textContent = formatRupiahVal(effectiveHpp);
                    }

                    // If cost_source is 'hpp', auto-sync cost input with effective HPP!
                    if (costSource === 'hpp' && costInput) {
                        costInput.value = effectiveHpp > 0 ? (effectiveHpp % 1 === 0 ? effectiveHpp : effectiveHpp.toFixed(2)) : '0';
                    }

                    // Margin badge calculation
                    const activeCost = parseFloat(costInput?.value) || 0;
                    const marginBadge = document.getElementById('catalogMarginBadge');
                    if (marginBadge) {
                        if (sellingPrice > 0) {
                            const marginAmt = sellingPrice - activeCost;
                            const marginPct = (marginAmt / sellingPrice) * 100;
                            const sign = marginPct >= 0 ? '+' : '';
                            marginBadge.textContent = `Margin: ${formatRupiahVal(marginAmt)} (${sign}${marginPct.toFixed(1)}%)`;
                            marginBadge.className = marginPct >= 0
                                ? 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                : 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300';
                        } else {
                            marginBadge.textContent = 'Margin: --';
                            marginBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
                        }
                    }
                };

                const reindexRecipeRows = () => {
                    const container = recipeContainer();
                    if (!container) return;
                    const rows = container.querySelectorAll('.recipe-row');
                    if (rows.length === 0) {
                        if (emptyHint()) emptyHint().classList.remove('hidden');
                    } else {
                        if (emptyHint()) emptyHint().classList.add('hidden');
                    }
                    rows.forEach((r, idx) => {
                        const sel = r.querySelector('.recipe-component-select');
                        const qty = r.querySelector('.recipe-qty-input');
                        if (sel) sel.name = `recipe_items[${idx}][component_product_id]`;
                        if (qty) qty.name = `recipe_items[${idx}][quantity]`;
                    });
                };

                const clearRecipeRows = () => {
                    const container = recipeContainer();
                    if (!container) return;
                    if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
                        $(container).find('.recipe-component-select').each(function () {
                            if ($(this).data('select2')) {
                                $(this).select2('destroy');
                            }
                        });
                    }
                    container.innerHTML = '';
                };

                const addRecipeRow = (item = null) => {
                    const container = recipeContainer();
                    if (!container) return;

                    const rowIndex = container.querySelectorAll('.recipe-row').length;
                    const rawMaterials = getRawMaterials();

                    const row = document.createElement('div');
                    row.className = 'recipe-row flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-2.5 rounded-xl border border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs shadow-xs';

                    const selectedCompId = item ? (item.component_product_id || item.id) : '';
                    const initialQty = item ? (item.quantity ?? 1) : 1;

                    let optionsHtml = '<option value="">-- Cari & Pilih Bahan Baku --</option>';
                    rawMaterials.forEach(mat => {
                        const isSel = String(mat.id) === String(selectedCompId) ? 'selected' : '';
                        optionsHtml += `<option value="${mat.id}" data-cost="${mat.cost}" data-unit="${mat.unit}" ${isSel}>${mat.name} (${mat.sku}) - Rp ${Math.round(mat.cost).toLocaleString('id-ID')}/${mat.unit}</option>`;
                    });

                    row.innerHTML = `
                        <div class="flex-1 min-w-0">
                            <select name="recipe_items[${rowIndex}][component_product_id]" required class="recipe-component-select w-full">
                                ${optionsHtml}
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5 w-full sm:w-36">
                            <input type="number" name="recipe_items[${rowIndex}][quantity]" step="any" min="0.0001" required value="${initialQty}" placeholder="Takaran" class="recipe-qty-input w-20 h-9 px-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-right text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <span class="recipe-row-unit text-[11px] font-semibold text-slate-500 dark:text-slate-400 w-12 truncate">-</span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-32">
                            <span class="recipe-row-subtotal font-bold text-slate-800 dark:text-slate-200 text-xs tabular-nums text-right">Rp 0</span>
                            <button type="button" class="recipe-remove-btn p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors" title="Hapus Bahan">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    `;

                    container.appendChild(row);

                    const sel = row.querySelector('.recipe-component-select');
                    const qty = row.querySelector('.recipe-qty-input');
                    const removeBtn = row.querySelector('.recipe-remove-btn');

                    if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
                        $(sel).select2({
                            dropdownParent: $('#catalogModal'),
                            placeholder: '-- Cari & Pilih Bahan Baku --',
                            allowClear: true,
                            width: '100%',
                        }).on('change', () => {
                            updateLiveCalculations();
                        });
                    } else {
                        sel?.addEventListener('change', updateLiveCalculations);
                    }

                    qty?.addEventListener('input', updateLiveCalculations);
                    removeBtn?.addEventListener('click', () => {
                        if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
                            if ($(sel).data('select2')) {
                                $(sel).select2('destroy');
                            }
                        }
                        row.remove();
                        reindexRecipeRows();
                        updateLiveCalculations();
                    });

                    if (emptyHint()) emptyHint().classList.add('hidden');
                    updateLiveCalculations();
                };

                const setValue = (form, name, value) => {
                    const field = form.elements.namedItem(name);
                    if (field) {
                        field.value = value === null || value === undefined ? '' : value;
                    }
                };

                const applyKind = (form, kind, editing) => {
                    form.querySelectorAll('[data-catalog-field="product"], [data-catalog-field="service"]').forEach((group) => {
                        group.classList.toggle('hidden', group.getAttribute('data-catalog-field') !== kind);
                    });

                    const createGroup = form.querySelector('[data-catalog-field="product-create"]');
                    if (createGroup) {
                        createGroup.classList.toggle('hidden', editing || kind !== 'product');
                    }

                    const skuHint = document.getElementById('catalogProductSkuHint');
                    if (skuHint) {
                        skuHint.textContent = kind === 'service' ? '(opsional)' : '(wajib)';
                    }
                };

                const setHeading = (subtitle, title) => {
                    const subEl = document.getElementById('catalogModalSubtitle');
                    const titleEl = document.getElementById('catalogModalTitle');
                    if (subEl) subEl.textContent = subtitle;
                    if (titleEl) titleEl.textContent = title;
                };

                const generateRandomSku = (overrideKind = null, overrideSellable = null) => {
                    const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
                    let code = '';
                    for (let i = 0; i < 6; i++) {
                        code += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    const form = productForm();
                    const kind = overrideKind !== null ? overrideKind : (form?.elements.namedItem('kind')?.value || 'product');
                    const sellableCheckbox = document.getElementById('catalogProductIsSellable');
                    const isSellable = overrideSellable !== null ? overrideSellable : (sellableCheckbox ? sellableCheckbox.checked : true);

                    let prefix = 'SKU';
                    if (kind === 'service') {
                        prefix = 'SRV';
                    } else if (!isSellable) {
                        prefix = 'RAW';
                    }

                    return `${prefix}-${code}`;
                };

                const handleGenerateSku = () => {
                    const skuInput = document.getElementById('catalogProductSku');
                    if (!skuInput) return;
                    skuInput.value = generateRandomSku();
                    skuInput.dispatchEvent(new Event('input', { bubbles: true }));
                    skuInput.dispatchEvent(new Event('change', { bubbles: true }));
                };

                document.getElementById('btnGenerateSkuLink')?.addEventListener('click', handleGenerateSku);
                document.getElementById('btnGenerateSkuInline')?.addEventListener('click', handleGenerateSku);

                const sellableToggle = document.getElementById('catalogProductIsSellable');
                sellableToggle?.addEventListener('change', () => {
                    const isEdit = Boolean(currentEditingProductId);
                    const prefix = isEdit ? 'Edit' : 'Tambah';
                    const isService = productForm()?.elements.namedItem('kind')?.value === 'service';
                    if (!isService) {
                        setHeading(prefix, sellableToggle.checked ? 'Produk' : 'Bahan Baku');
                    }
                });

                const showProductForm = (form) => {
                    form.classList.remove('hidden');
                    form.classList.add('flex');
                    const catF = categoryForm();
                    if (catF) {
                        catF.classList.add('hidden');
                        catF.classList.remove('flex');
                    }
                };

                const showCategoryForm = (form) => {
                    form.classList.remove('hidden');
                    form.classList.add('flex');
                    const prodF = productForm();
                    if (prodF) {
                        prodF.classList.add('hidden');
                        prodF.classList.remove('flex');
                    }
                };

                const open = (trigger) => {
                    const el = modalEl();
                    if (!el) return;

                    lastTrigger = trigger || null;
                    el.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';

                    // Reset scroll to top so user always sees the beginning of the form
                    const activeForm = productForm()?.classList.contains('hidden') ? categoryForm() : productForm();
                    const formBody = activeForm?.querySelector('.overflow-y-auto');
                    if (formBody) {
                        formBody.scrollTop = 0;
                    }

                    requestAnimationFrame(() => {
                        const focusables = Array.from(el.querySelectorAll(focusableSelector));
                        if (focusables.length > 0) {
                            focusables[0].focus({ preventScroll: true });
                        } else {
                            el.focus({ preventScroll: true });
                        }
                    });
                };

                const close = () => {
                    const el = modalEl();
                    if (!el || el.classList.contains('hidden')) return;

                    if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
                        $('.recipe-component-select').each(function () {
                            if ($(this).data('select2')) {
                                $(this).select2('close');
                            }
                        });
                    }

                    el.classList.add('hidden');
                    document.body.style.overflow = '';

                    if (lastTrigger && document.contains(lastTrigger) && typeof lastTrigger.focus === 'function') {
                        lastTrigger.focus({ preventScroll: true });
                    }
                    lastTrigger = null;
                };

                const openCreate = (kind, trigger) => {
                    const form = productForm();
                    const catForm = categoryForm();
                    const el = modalEl();
                    if (!form || !catForm || !el) return;

                    if (kind === 'category') {
                        catForm.reset();
                        catForm.setAttribute('action', catForm.dataset.storeUrl);
                        setValue(catForm, '_method', 'POST');
                        setValue(catForm, 'status', 'active');
                        showCategoryForm(catForm);
                        setHeading('Tambah', 'Kategori');
                    } else {
                        form.reset();
                        form.setAttribute('action', form.dataset.storeUrl);
                        setValue(form, '_method', 'POST');
                        setValue(form, 'kind', kind);
                        setValue(form, 'status', 'active');
                        setValue(form, 'stock', '0');
                        setValue(form, 'min_stock', '0');
                        setValue(form, 'min_quantity', '0');

                        currentEditingProductId = null;
                        currentHppCalculated = 0;
                        clearRecipeRows();
                        if (emptyHint()) emptyHint().classList.remove('hidden');

                        const isSupply = trigger?.getAttribute('data-catalog-target') === 'supply' || trigger?.getAttribute('data-catalog-kind') === 'supply';

                        setValue(form, 'cost', '0');
                        setValue(form, 'cost_source', 'manual');
                        setValue(form, 'hpp_method', 'average');
                        setValue(form, 'hpp_overhead_fixed', '0');
                        setValue(form, 'hpp_overhead_percent', '0');
                        if (recipeSection()) recipeSection().classList.add('hidden');
                        const calcAtCreate = document.getElementById('catalogHppCalculatedAt');
                        if (calcAtCreate) calcAtCreate.textContent = '';
                        updateLiveCalculations();

                        const sellableCreate = document.getElementById('catalogProductIsSellable');
                        if (sellableCreate) sellableCreate.checked = !isSupply;

                        if (isSupply) {
                            setValue(form, 'price', '0');
                        }

                        const defaultUnit = isSupply ? 'gram' : (el.dataset.defaultUnit || 'pcs');
                        if (!form.elements.namedItem('unit').value) {
                            setValue(form, 'unit', defaultUnit);
                        }
                        if (kind === 'service' && !form.elements.namedItem('pricing_unit').value) {
                            setValue(form, 'pricing_unit', defaultUnit);
                        }

                        // Auto-fill fresh generated SKU on create
                        const skuInput = document.getElementById('catalogProductSku');
                        if (skuInput) {
                            skuInput.value = generateRandomSku(kind, !isSupply);
                        }

                        applyKind(form, kind, false);
                        showProductForm(form);
                        if (kind === 'service') {
                            setHeading('Tambah', 'Layanan');
                        } else if (isSupply) {
                            setHeading('Tambah', 'Bahan Baku');
                        } else {
                            setHeading('Tambah', 'Produk');
                        }
                    }

                    open(trigger);
                };

                const openEdit = (kind, raw, trigger) => {
                    if (!raw) return;

                    if (kind === 'category') {
                        const catForm = categoryForm();
                        if (!catForm) return;
                        showCategoryForm(catForm);
                        catForm.reset();
                        catForm.setAttribute('action', catForm.dataset.updateUrlTemplate.replace('__ID__', raw.id));
                        setValue(catForm, '_method', 'PATCH');
                        setValue(catForm, 'name', raw.name || '');
                        setValue(catForm, 'status', raw.status_raw || raw.status || 'active');
                        setHeading('Edit', 'Kategori');
                        open(trigger);

                        return;
                    }

                    const form = productForm();
                    if (!form) return;
                    showProductForm(form);
                    form.reset();
                    form.setAttribute('action', form.dataset.updateUrlTemplate.replace('__ID__', raw.id));
                    setValue(form, '_method', 'PATCH');
                    setValue(form, 'kind', kind);
                    setValue(form, 'name', raw.name || '');
                    setValue(form, 'category_id', raw.category_id === null || raw.category_id === undefined ? '' : raw.category_id);
                    setValue(form, 'price', raw.price ?? '');
                    setValue(form, 'sku', raw.sku || '');
                    setValue(form, 'unit', raw.unit || '');
                    setValue(form, 'status', raw.status_raw || raw.status || 'active');

                    if (kind === 'service') {
                        setValue(form, 'pricing_unit', raw.pricing_unit || '');
                        setValue(form, 'min_quantity', raw.min_quantity ?? '0');
                        setValue(form, 'estimated_duration', raw.estimated_duration || '');
                    } else {
                        currentEditingProductId = raw.id || null;
                        currentHppCalculated = parseFloat(raw.hpp) || 0;
                        setValue(form, 'barcode', raw.barcode || '');
                        setValue(form, 'cost', raw.cost ?? '0');
                        setValue(form, 'min_stock', raw.min_stock ?? '0');
                        setValue(form, 'cost_source', raw.cost_source || 'manual');
                        setValue(form, 'hpp_method', raw.hpp_method || 'average');
                        setValue(form, 'hpp_overhead_fixed', raw.hpp_overhead_fixed ?? '0');
                        setValue(form, 'hpp_overhead_percent', raw.hpp_overhead_percent ?? '0');

                        const calcAtEdit = document.getElementById('catalogHppCalculatedAt');
                        if (calcAtEdit) {
                            calcAtEdit.textContent = raw.hpp_calculated_at ? `(${raw.hpp_calculated_at})` : '';
                        }

                        clearRecipeRows();
                        if (raw.recipe_items && raw.recipe_items.length > 0) {
                            raw.recipe_items.forEach(ri => addRecipeRow(ri));
                        } else {
                            if (emptyHint()) emptyHint().classList.remove('hidden');
                        }

                        const isRecipe = (raw.hpp_method === 'recipe');
                        if (recipeSection()) recipeSection().classList.toggle('hidden', !isRecipe);

                        const isSellable = raw.is_sellable !== false && raw.is_sellable !== 0;
                        const sellableEdit = document.getElementById('catalogProductIsSellable');
                        if (sellableEdit) {
                            sellableEdit.checked = isSellable;
                        }

                        updateLiveCalculations();

                        applyKind(form, kind, true);
                        const isSupply = !isSellable;
                        setHeading('Edit', kind === 'service' ? 'Layanan' : (isSupply ? 'Bahan Baku' : 'Produk'));
                        open(trigger);
                    }
                };

                const parseRaw = (el) => {
                    const raw = el.getAttribute('data-catalog-raw');
                    if (!raw) return null;
                    try {
                        return JSON.parse(raw);
                    } catch (error) {
                        return null;
                    }
                };

                document.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof Element)) return;

                    if (target.closest('[data-close-catalog-modal]')) {
                        close();
                        return;
                    }

                    const trigger = target.closest('[data-open-catalog-modal]');
                    if (!trigger) return;

                    event.preventDefault();
                    const mode = trigger.getAttribute('data-catalog-mode') || 'create';
                    const kind = trigger.getAttribute('data-catalog-kind') || 'product';

                    if (mode === 'edit') {
                        openEdit(kind, parseRaw(trigger), trigger);
                    } else {
                        openCreate(kind, trigger);
                    }
                });

                document.addEventListener('keydown', (event) => {
                    const el = modalEl();
                    if (!el || el.classList.contains('hidden')) return;

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close();
                        return;
                    }

                    if (event.key !== 'Tab') return;

                    const focusables = Array.from(el.querySelectorAll(focusableSelector));
                    if (focusables.length === 0) return;

                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    const activeIndex = focusables.indexOf(document.activeElement);

                    if (event.shiftKey) {
                        if (activeIndex <= 0) {
                            event.preventDefault();
                            last.focus({ preventScroll: true });
                        }
                        return;
                    }

                    if (activeIndex === -1 || activeIndex === focusables.length - 1) {
                        event.preventDefault();
                        first.focus({ preventScroll: true });
                    }
                });

                // Prevent double submits: disable the button once a catalog form
                // is submitted. Unique constraints also reject duplicates server-side.
                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) return;
                    if (form.id !== 'catalogProductForm' && form.id !== 'catalogCategoryForm') return;

                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = true;
                        const label = button.querySelector('[data-submit-label]');
                        if (label) label.textContent = 'Menyimpan…';
                    });
                });

                // HPP & Recipe event listeners
                const addRecipeBtn = document.getElementById('catalogAddRecipeItemBtn');
                if (addRecipeBtn) {
                    addRecipeBtn.addEventListener('click', () => addRecipeRow());
                }

                const applyHppBtn = document.getElementById('catalogApplyHppBtn');
                if (applyHppBtn) {
                    applyHppBtn.addEventListener('click', () => {
                        const form = productForm();
                        const costInput = form?.elements.namedItem('cost');
                        const hppMethodSelect = form?.elements.namedItem('hpp_method');
                        if (!costInput) return;

                        let targetHpp = currentHppCalculated;
                        if (hppMethodSelect?.value === 'recipe') {
                            const totalEstEl = document.getElementById('catalogRecipeTotalEstimate');
                            const cleanText = totalEstEl?.textContent?.replace(/[^0-9]/g, '') || '0';
                            targetHpp = parseFloat(cleanText) || 0;
                        }

                        costInput.value = targetHpp > 0 ? (targetHpp % 1 === 0 ? targetHpp : targetHpp.toFixed(2)) : '0';
                        updateLiveCalculations();
                    });
                }

                const hppMethodSelect = document.getElementById('catalogHppMethod');
                if (hppMethodSelect) {
                    hppMethodSelect.addEventListener('change', () => {
                        const isRecipe = hppMethodSelect.value === 'recipe';
                        const sec = recipeSection();
                        if (sec) {
                            sec.classList.toggle('hidden', !isRecipe);
                        }
                        updateLiveCalculations();
                    });
                }

                const costSourceSelect = document.getElementById('catalogCostSource');
                if (costSourceSelect) {
                    costSourceSelect.addEventListener('change', updateLiveCalculations);
                }

                ['catalogProductPrice', 'catalogProductCost', 'catalogHppOverheadFixed', 'catalogHppOverheadPercent'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.addEventListener('input', updateLiveCalculations);
                    }
                });

                if (window.__catalogModalOpenOnError) {
                    const kind = window.__catalogModalOpenOnError;
                    window.__catalogModalOpenOnError = null;
                    requestAnimationFrame(() => openCreate(kind, null));
                }
            })();
        </script>
    @endif
</x-layouts::app>
