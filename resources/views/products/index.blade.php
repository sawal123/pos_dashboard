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
                    :active-tab="$activeTab"
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
            <x-products.catalog-modal :categories="$categories" :business-type="$dashboardBusinessType ?? null" />
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

                if (isService) {
                    costRow.classList.add('hidden');
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

                const modalEl = () => document.getElementById('catalogModal');
                const productForm = () => document.getElementById('catalogProductForm');
                const categoryForm = () => document.getElementById('catalogCategoryForm');

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

                const showProductForm = (form) => {
                    form.classList.remove('hidden');
                    categoryForm()?.classList.add('hidden');
                };

                const showCategoryForm = (form) => {
                    form.classList.remove('hidden');
                    productForm()?.classList.add('hidden');
                };

                const open = (trigger) => {
                    const el = modalEl();
                    if (!el) return;

                    lastTrigger = trigger || null;
                    el.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';

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

                        const defaultUnit = el.dataset.defaultUnit || 'pcs';
                        if (!form.elements.namedItem('unit').value) {
                            setValue(form, 'unit', defaultUnit);
                        }
                        if (kind === 'service' && !form.elements.namedItem('pricing_unit').value) {
                            setValue(form, 'pricing_unit', defaultUnit);
                        }

                        applyKind(form, kind, false);
                        showProductForm(form);
                        setHeading('Tambah', kind === 'service' ? 'Layanan' : 'Produk');
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
                        setValue(form, 'barcode', raw.barcode || '');
                        setValue(form, 'cost', raw.cost ?? '0');
                        setValue(form, 'min_stock', raw.min_stock ?? '0');
                    }

                    applyKind(form, kind, true);
                    setHeading('Edit', kind === 'service' ? 'Layanan' : 'Produk');
                    open(trigger);
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

                if (window.__catalogModalOpenOnError) {
                    const kind = window.__catalogModalOpenOnError;
                    window.__catalogModalOpenOnError = null;
                    requestAnimationFrame(() => openCreate(kind, null));
                }
            })();
        </script>
    @endif
</x-layouts::app>
