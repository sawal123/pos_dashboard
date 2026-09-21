@php
    $loadFixtures = require resource_path('views/products/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['products']) || !empty($fixtureData['services']);
@endphp

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
        <x-products.summary-cards :summary="$fixtureData['summary']" />

        {{-- ==================== 2. TABS NAVIGATOR ==================== --}}
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800" role="tablist" aria-label="Navigasi Katalog">
            <button
                type="button"
                id="tabProducts"
                role="tab"
                aria-selected="true"
                aria-controls="panelProducts"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 transition-colors focus:outline-none"
            >
                <i data-lucide="package" class="w-4 h-4"></i>
                <span>Produk</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 tabular-nums">
                    {{ count($fixtureData['products']) }}
                </span>
            </button>
            <button
                type="button"
                id="tabServices"
                role="tab"
                aria-selected="false"
                aria-controls="panelServices"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors focus:outline-none"
            >
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                <span>Layanan</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 tabular-nums">
                    {{ count($fixtureData['services']) }}
                </span>
            </button>
            <button
                type="button"
                id="tabCategories"
                role="tab"
                aria-selected="false"
                aria-controls="panelCategories"
                class="catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors focus:outline-none"
            >
                <i data-lucide="tags" class="w-4 h-4"></i>
                <span>Kategori</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 tabular-nums">
                    {{ count($fixtureData['categories']) }}
                </span>
            </button>
        </div>

        {{-- ==================== 3. FILTER BAR ==================== --}}
        <x-products.filter-bar :categories="$fixtureData['categories']" />

        {{-- ==================== 4. TAB PANELS / DATA LIST ==================== --}}
        @if($hasData)
            {{-- Tab 1: Produk Panel --}}
            <div id="panelProducts" role="tabpanel" aria-labelledby="tabProducts" class="catalog-panel space-y-4">
                <x-products.product-table :products="$fixtureData['products']" />
            </div>

            {{-- Tab 2: Layanan Panel --}}
            <div id="panelServices" role="tabpanel" aria-labelledby="tabServices" class="catalog-panel space-y-4 hidden">
                <x-products.service-table :services="$fixtureData['services']" />
            </div>

            {{-- Tab 3: Kategori Panel --}}
            <div id="panelCategories" role="tabpanel" aria-labelledby="tabCategories" class="catalog-panel space-y-4 hidden">
                <x-products.category-table :categories="$fixtureData['categories']" />
            </div>

            {{-- Mobile Cards (Unified Container) --}}
            <div id="mobileCardsWrapper">
                <x-products.mobile-cards
                    :products="$fixtureData['products']"
                    :services="$fixtureData['services']"
                    :categories="$fixtureData['categories']"
                />
            </div>

            {{-- Filter Zero Match Empty State --}}
            <x-products.empty-state mode="no-results" />

            {{-- ==================== 5. PAGINATION ==================== --}}
            <div id="catalogPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan <span id="catalogVisibleCount" class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['products']) }}</span> item
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Katalog">
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-current="page"
                    >
                        1
                    </button>
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Berikutnya
                    </button>
                </nav>
            </div>
        @else
            {{-- Initial Empty State (Non-local / Production before data integration) --}}
            <x-products.empty-state mode="no-data" />
        @endif

        {{-- ==================== 6. DETAIL DRAWER ==================== --}}
        <x-products.detail-drawer />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initProductsPage() {
            const root = document.querySelector('main[data-products-page="true"]');
            if (!root || root.dataset.productsInitialized === 'true') {
                return;
            }
            root.dataset.productsInitialized = 'true';

            // Active Tab State: 'products' | 'services' | 'categories'
            let activeTab = 'products';

            // Tab Buttons & Panels
            const tabProducts = document.getElementById('tabProducts');
            const tabServices = document.getElementById('tabServices');
            const tabCategories = document.getElementById('tabCategories');
            const tabButtons = [tabProducts, tabServices, tabCategories].filter(Boolean);

            const panelProducts = document.getElementById('panelProducts');
            const panelServices = document.getElementById('panelServices');
            const panelCategories = document.getElementById('panelCategories');

            const mobileProductCards = document.getElementById('mobileProductCards');
            const mobileServiceCards = document.getElementById('mobileServiceCards');
            const mobileCategoryCards = document.getElementById('mobileCategoryCards');

            // Filter Elements
            const searchInput = document.getElementById('searchCatalogInput');
            const filterCategory = document.getElementById('filterCategory');
            const filterStatus = document.getElementById('filterStatus');
            const filterStockStatus = document.getElementById('filterStockStatus');
            const stockStatusFilterWrapper = document.getElementById('stockStatusFilterWrapper');
            const resetFilterBtn = document.getElementById('resetFilterBtn');
            const addCatalogBtnText = document.getElementById('addCatalogBtnText');

            const filterEmptyState = document.getElementById('productFilterEmptyState');
            const filterEmptyTitle = document.getElementById('productFilterEmptyTitle');
            const paginationEl = document.getElementById('catalogPagination');
            const visibleCountEl = document.getElementById('catalogVisibleCount');

            // Drawer Elements
            const drawerWrapper = document.getElementById('productDrawerWrapper');
            const drawerBackdrop = document.getElementById('productDrawerBackdrop');
            const drawerPanel = document.getElementById('productDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeProductDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeProductDrawerFooterBtn');

            let lastTriggerElement = null;

            function formatRupiah(num) {
                return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
            }

            // Tab Switching Logic with ARIA & Keyboard Focus
            function setTab(tabName, shouldFocus = false) {
                activeTab = tabName;

                const tabs = [
                    { name: 'products', btn: tabProducts, panel: panelProducts, mobile: mobileProductCards, label: 'Tambah Produk', placeholder: 'Cari produk, SKU, atau barcode...' },
                    { name: 'services', btn: tabServices, panel: panelServices, mobile: mobileServiceCards, label: 'Tambah Layanan', placeholder: 'Cari layanan atau SKU...' },
                    { name: 'categories', btn: tabCategories, panel: panelCategories, mobile: mobileCategoryCards, label: 'Tambah Kategori', placeholder: 'Cari kategori...' },
                ];

                tabs.forEach(t => {
                    const isActive = t.name === tabName;
                    if (t.btn) {
                        t.btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        t.btn.setAttribute('tabindex', isActive ? '0' : '-1');
                        if (isActive) {
                            t.btn.className = 'catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg';
                            if (shouldFocus) {
                                t.btn.focus();
                            }
                        } else {
                            t.btn.className = 'catalog-tab-btn flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg';
                        }
                    }

                    if (t.panel) {
                        t.panel.classList.toggle('hidden', !isActive);
                    }
                    if (t.mobile) {
                        t.mobile.classList.toggle('hidden', !isActive);
                    }

                    if (isActive) {
                        if (addCatalogBtnText) addCatalogBtnText.textContent = t.label;
                        if (searchInput) searchInput.placeholder = t.placeholder;
                    }
                });

                // Toggle stock status filter visibility (only relevant on products)
                if (stockStatusFilterWrapper) {
                    stockStatusFilterWrapper.style.display = tabName === 'products' ? '' : 'none';
                }

                // Filter category is hidden on category tab itself
                if (filterCategory) {
                    filterCategory.parentElement.style.display = tabName === 'categories' ? 'none' : '';
                }

                applyFilters();
            }

            if (tabProducts) tabProducts.addEventListener('click', () => setTab('products'));
            if (tabServices) tabServices.addEventListener('click', () => setTab('services'));
            if (tabCategories) tabCategories.addEventListener('click', () => setTab('categories'));

            // Keyboard navigation for Tabs (ArrowLeft, ArrowRight, Home, End)
            const tabListEl = document.querySelector('[role="tablist"]');
            if (tabListEl) {
                tabListEl.addEventListener('keydown', (e) => {
                    const currentIndex = tabButtons.indexOf(document.activeElement);
                    if (currentIndex === -1) return;

                    let newIndex = currentIndex;
                    if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        newIndex = (currentIndex + 1) % tabButtons.length;
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        newIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        newIndex = 0;
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        newIndex = tabButtons.length - 1;
                    } else {
                        return;
                    }

                    const targetBtn = tabButtons[newIndex];
                    if (targetBtn === tabProducts) setTab('products', true);
                    else if (targetBtn === tabServices) setTab('services', true);
                    else if (targetBtn === tabCategories) setTab('categories', true);
                });
            }

            // Client-side Filtering
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const category = filterCategory?.value || 'all';
                const status = filterStatus?.value || 'all';
                const stockStatus = filterStockStatus?.value || 'all';

                let matchedCount = 0;

                if (activeTab === 'products') {
                    const rows = document.querySelectorAll('.product-row');
                    const cards = document.querySelectorAll('.product-card');

                    const checkMatch = (el) => {
                        const name = (el.getAttribute('data-name') || '').toLowerCase();
                        const sku = (el.getAttribute('data-sku') || '').toLowerCase();
                        const barcode = (el.getAttribute('data-barcode') || '').toLowerCase();
                        const elCategory = el.getAttribute('data-category');
                        const elStatus = el.getAttribute('data-status');
                        const elStockStatus = el.getAttribute('data-stock-status');

                        const matchSearch = !search || name.includes(search) || sku.includes(search) || barcode.includes(search);
                        const matchCategory = category === 'all' || elCategory === category;
                        const matchStatus = status === 'all' || elStatus === status;
                        const matchStockStatus = stockStatus === 'all' || elStockStatus === stockStatus;

                        return matchSearch && matchCategory && matchStatus && matchStockStatus;
                    };

                    rows.forEach(r => {
                        const m = checkMatch(r);
                        r.style.display = m ? '' : 'none';
                        if (m) matchedCount++;
                    });
                    cards.forEach(c => {
                        c.style.display = checkMatch(c) ? '' : 'none';
                    });

                    if (filterEmptyTitle) filterEmptyTitle.textContent = 'Produk Tidak Ditemukan';

                } else if (activeTab === 'services') {
                    const rows = document.querySelectorAll('.service-row');
                    const cards = document.querySelectorAll('.service-card');

                    const checkMatch = (el) => {
                        const name = (el.getAttribute('data-name') || '').toLowerCase();
                        const sku = (el.getAttribute('data-sku') || '').toLowerCase();
                        const elCategory = el.getAttribute('data-category');
                        const elStatus = el.getAttribute('data-status');

                        const matchSearch = !search || name.includes(search) || sku.includes(search);
                        const matchCategory = category === 'all' || elCategory === category;
                        const matchStatus = status === 'all' || elStatus === status;

                        return matchSearch && matchCategory && matchStatus;
                    };

                    rows.forEach(r => {
                        const m = checkMatch(r);
                        r.style.display = m ? '' : 'none';
                        if (m) matchedCount++;
                    });
                    cards.forEach(c => {
                        c.style.display = checkMatch(c) ? '' : 'none';
                    });

                    if (filterEmptyTitle) filterEmptyTitle.textContent = 'Layanan Tidak Ditemukan';

                } else if (activeTab === 'categories') {
                    const rows = document.querySelectorAll('.category-row');
                    const cards = document.querySelectorAll('.category-card');

                    const checkMatch = (el) => {
                        const name = (el.getAttribute('data-name') || '').toLowerCase();
                        const elStatus = el.getAttribute('data-status');

                        const matchSearch = !search || name.includes(search);
                        const matchStatus = status === 'all' || elStatus === status;

                        return matchSearch && matchStatus;
                    };

                    rows.forEach(r => {
                        const m = checkMatch(r);
                        r.style.display = m ? '' : 'none';
                        if (m) matchedCount++;
                    });
                    cards.forEach(c => {
                        c.style.display = checkMatch(c) ? '' : 'none';
                    });

                    if (filterEmptyTitle) filterEmptyTitle.textContent = 'Kategori Tidak Ditemukan';
                }

                if (visibleCountEl) visibleCountEl.textContent = matchedCount;

                const activePanel = activeTab === 'products' ? panelProducts : (activeTab === 'services' ? panelServices : panelCategories);
                const activeMobile = activeTab === 'products' ? mobileProductCards : (activeTab === 'services' ? mobileServiceCards : mobileCategoryCards);

                if (matchedCount === 0) {
                    if (filterEmptyState) filterEmptyState.classList.remove('hidden');
                    if (activePanel) activePanel.classList.add('hidden');
                    if (activeMobile) activeMobile.classList.add('hidden');
                    if (paginationEl) paginationEl.classList.add('hidden');
                } else {
                    if (filterEmptyState) filterEmptyState.classList.add('hidden');
                    if (activePanel) activePanel.classList.remove('hidden');
                    if (activeMobile) activeMobile.classList.remove('hidden');
                    if (paginationEl) paginationEl.classList.remove('hidden');
                }
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (filterCategory) filterCategory.addEventListener('change', applyFilters);
            if (filterStatus) filterStatus.addEventListener('change', applyFilters);
            if (filterStockStatus) filterStockStatus.addEventListener('change', applyFilters);

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterCategory) filterCategory.value = 'all';
                    if (filterStatus) filterStatus.value = 'all';
                    if (filterStockStatus) filterStockStatus.value = 'all';
                    applyFilters();
                });
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

                const isService = itemData.kind === 'service';

                // Subheading & Title
                document.getElementById('productDrawerSubheading').textContent = isService ? 'Detail Layanan' : 'Detail Produk';
                document.getElementById('productDrawerTitle').textContent = itemData.name || '-';
                document.getElementById('detailItemKind').textContent = isService ? 'Layanan' : 'Produk';

                // Status Badge (DOM Safe)
                const statusBadge = document.getElementById('detailItemStatusBadge');
                statusBadge.textContent = '';
                const statusDot = document.createElement('span');
                const statusText = document.createElement('span');

                if (itemData.status === 'active') {
                    statusBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    statusDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    statusText.textContent = 'Aktif';
                } else {
                    statusBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                    statusDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    statusText.textContent = 'Nonaktif';
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

                    document.getElementById('detailServiceMinQty').textContent = `${itemData.min_quantity || 1} ${itemData.unit || 'kg'}`;
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
                    if (stockNum < 0) {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-950/80 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300';
                        stockBadge.textContent = 'Minus';
                    } else if (stockNum === 0) {
                        stockBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                        stockBadge.textContent = 'Habis';
                    } else if (stockNum <= minStockNum) {
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

            // Ensure initial state is consistently set to Products
            setTab('products');
        }

        initProductsPage();

        if (!window.__productsListenersBound) {
            window.__productsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initProductsPage);
            document.addEventListener('livewire:navigated', initProductsPage);
        }
    </script>
</x-layouts::app>
