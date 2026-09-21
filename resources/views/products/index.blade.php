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
            :filters="$filters"
        />

        {{-- ==================== 4. DATA LIST / EMPTY STATE ==================== --}}
        @if($items->total() > 0)
            @if($activeTab === 'products')
                <div id="panelProducts" role="tabpanel" aria-labelledby="tabProducts" class="catalog-panel space-y-4">
                    <x-products.product-table :products="$items" />
                </div>
            @elseif($activeTab === 'services')
                <div id="panelServices" role="tabpanel" aria-labelledby="tabServices" class="catalog-panel space-y-4">
                    <x-products.service-table :services="$items" />
                </div>
            @elseif($activeTab === 'categories')
                <div id="panelCategories" role="tabpanel" aria-labelledby="tabCategories" class="catalog-panel space-y-4">
                    <x-products.category-table :categories="$items" />
                </div>
            @endif

            {{-- Mobile Cards (Unified Container) --}}
            <div id="mobileCardsWrapper">
                <x-products.mobile-cards
                    :items="$items"
                    :active-tab="$activeTab"
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

            if (drawerEditCatalogBtn) {
                drawerEditCatalogBtn.onclick = () => {
                    const isService = currentItemData && currentItemData.kind === 'service';
                    const msg = isService
                        ? 'Pengelolaan layanan dari dashboard belum tersedia.'
                        : 'Pengelolaan produk dari dashboard belum tersedia.';
                    showToast('info', msg);
                };
            }

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
</x-layouts::app>
