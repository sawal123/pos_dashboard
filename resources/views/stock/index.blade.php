@php
    $loadFixtures = require resource_path('views/stock/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['items']);
@endphp

<x-layouts::app :title="'Stok'">
    <main id="mainContent" data-stock-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== STOCK HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Stok
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau jumlah stok dan pergerakan inventori bisnis.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                    <i data-lucide="boxes" class="w-3.5 h-3.5"></i>
                    <span>Inventori & Stok</span>
                </span>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-stock.summary-cards :summary="$fixtureData['summary']" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-stock.filter-bar :categories="$fixtureData['categories']" />

        {{-- ==================== 3. DATA LIST / EMPTY STATE ==================== --}}
        @if($hasData)
            {{-- Desktop Table View (>= md) --}}
            <x-stock.table :items="$fixtureData['items']" />

            {{-- Mobile Cards View (< md) --}}
            <x-stock.mobile-cards :items="$fixtureData['items']" />

            {{-- Filter Empty State (Shown when search/filter has no match) --}}
            <x-stock.empty-state mode="no-results" />

            {{-- ==================== 4. PAGINATION ==================== --}}
            <div id="stockPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan <span id="stockVisibleCount" class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['items']) }}</span> dari <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['items']) }}</span> item
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Stok">
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
            <x-stock.empty-state mode="no-data" />
        @endif

        {{-- ==================== 5. DETAIL MOVEMENT DRAWER ==================== --}}
        <x-stock.detail-drawer />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initStockPage() {
            const root = document.querySelector('main[data-stock-page="true"]');
            if (!root || root.dataset.stockInitialized === 'true') {
                return;
            }
            root.dataset.stockInitialized = 'true';

            const searchInput = document.getElementById('searchStockInput');
            const filterCategory = document.getElementById('filterStockCategory');
            const filterStatus = document.getElementById('filterStockStatus');
            const resetFilterBtn = document.getElementById('resetStockFilterBtn');

            const rows = document.querySelectorAll('.stock-row');
            const cards = document.querySelectorAll('.stock-card');
            const filterEmptyState = document.getElementById('stockFilterEmptyState');
            const desktopTable = document.getElementById('desktopStockTable')?.closest('.rounded-2xl');
            const mobileContainer = document.getElementById('mobileStockCards');
            const paginationEl = document.getElementById('stockPagination');
            const visibleCountEl = document.getElementById('stockVisibleCount');

            // Drawer Elements
            const drawerWrapper = document.getElementById('stockDrawerWrapper');
            const drawerBackdrop = document.getElementById('stockDrawerBackdrop');
            const drawerPanel = document.getElementById('stockDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeStockDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeStockDrawerFooterBtn');

            let lastTriggerElement = null;

            // Client-side Filtering
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const category = filterCategory?.value || 'all';
                const status = filterStatus?.value || 'all';

                let matchedCount = 0;

                const checkMatch = (el) => {
                    const name = (el.getAttribute('data-name') || '').toLowerCase();
                    const sku = (el.getAttribute('data-sku') || '').toLowerCase();
                    const elCategory = el.getAttribute('data-category');
                    const elStatus = el.getAttribute('data-stock-status');

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

                if (visibleCountEl) visibleCountEl.textContent = matchedCount;

                if (matchedCount === 0) {
                    if (filterEmptyState) filterEmptyState.classList.remove('hidden');
                    if (desktopTable) desktopTable.classList.add('hidden');
                    if (mobileContainer) mobileContainer.classList.add('hidden');
                    if (paginationEl) paginationEl.classList.add('hidden');
                } else {
                    if (filterEmptyState) filterEmptyState.classList.add('hidden');
                    if (desktopTable) desktopTable.classList.remove('hidden');
                    if (mobileContainer) mobileContainer.classList.remove('hidden');
                    if (paginationEl) paginationEl.classList.remove('hidden');
                }
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (filterCategory) filterCategory.addEventListener('change', applyFilters);
            if (filterStatus) filterStatus.addEventListener('change', applyFilters);

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterCategory) filterCategory.value = 'all';
                    if (filterStatus) filterStatus.value = 'all';
                    applyFilters();
                });
            }

            // Basic Focus Trap for Detail Drawer
            function handleStockDrawerTrap(e) {
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

            // Safe DOM Rendering for Stock Detail Drawer
            function openDrawer(itemData) {
                if (!drawerWrapper || !itemData) return;

                document.getElementById('stockDrawerTitle').textContent = itemData.name || '-';
                document.getElementById('stockDetailSku').textContent = itemData.sku || '-';
                document.getElementById('stockDetailCurrent').textContent = `${itemData.current_stock} ${itemData.unit}`;
                document.getElementById('stockDetailMin').textContent = `${itemData.min_stock} ${itemData.unit}`;

                // Deterministic Status Badge (DOM Safe)
                const badgeEl = document.getElementById('stockDetailBadge');
                badgeEl.textContent = '';
                const dot = document.createElement('span');
                const label = document.createElement('span');

                const curStock = parseFloat(itemData.current_stock);
                const minStock = parseFloat(itemData.min_stock);
                let derivedStatus = itemData.stock_status;
                if (!derivedStatus) {
                    if (curStock < 0) {
                        derivedStatus = 'negative';
                    } else if (curStock === 0) {
                        derivedStatus = 'empty';
                    } else if (curStock <= minStock) {
                        derivedStatus = 'low';
                    } else {
                        derivedStatus = 'safe';
                    }
                }

                if (derivedStatus === 'negative') {
                    badgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-100 dark:bg-rose-950/80 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-rose-600';
                    label.textContent = 'Minus';
                } else if (derivedStatus === 'empty') {
                    badgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                    label.textContent = 'Habis';
                } else if (derivedStatus === 'low') {
                    badgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/70 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-500';
                    label.textContent = 'Menipis';
                } else {
                    badgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    label.textContent = 'Aman';
                }
                badgeEl.appendChild(dot);
                badgeEl.appendChild(label);

                // Populate Movement List via safe DOM methods
                const movementsContainer = document.getElementById('stockMovementsContainer');
                movementsContainer.textContent = '';

                if (itemData.movements && itemData.movements.length > 0) {
                    itemData.movements.forEach(m => {
                        const card = document.createElement('div');
                        card.className = 'p-3 sm:p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-1.5 text-xs';

                        const headerRow = document.createElement('div');
                        headerRow.className = 'flex items-center justify-between gap-2';

                        const typeSpan = document.createElement('span');
                        typeSpan.className = 'font-bold text-slate-800 dark:text-slate-200';
                        typeSpan.textContent = m.movement_type_label || m.movement_type;

                        const qtySpan = document.createElement('span');
                        const isPositive = String(m.quantity_change).startsWith('+');
                        qtySpan.className = `font-extrabold tabular-nums ${isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`;
                        qtySpan.textContent = `${m.quantity_change} ${itemData.unit}`;

                        headerRow.appendChild(typeSpan);
                        headerRow.appendChild(qtySpan);

                        const balanceRow = document.createElement('div');
                        balanceRow.className = 'flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400';

                        const changeP = document.createElement('p');
                        changeP.textContent = `${m.stock_before} → ${m.stock_after} ${itemData.unit}`;

                        const dateP = document.createElement('p');
                        dateP.className = 'font-mono text-[10px] text-slate-400';
                        dateP.textContent = m.occurred_at;

                        balanceRow.appendChild(changeP);
                        balanceRow.appendChild(dateP);

                        card.appendChild(headerRow);
                        card.appendChild(balanceRow);

                        // Reference & Category info (if available)
                        if (m.reference_id || m.category) {
                            const metaRow = document.createElement('div');
                            metaRow.className = 'flex items-center gap-2 pt-1 text-[11px] text-slate-500 dark:text-slate-400 flex-wrap';

                            if (m.category) {
                                const catBadge = document.createElement('span');
                                catBadge.className = 'inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-medium uppercase tracking-wider';
                                catBadge.textContent = m.category;
                                metaRow.appendChild(catBadge);
                            }

                            if (m.reference_id) {
                                const refSpan = document.createElement('span');
                                refSpan.className = 'font-mono text-[10px] text-slate-500 dark:text-slate-400';
                                refSpan.textContent = `Ref: ${m.reference_id}`;
                                metaRow.appendChild(refSpan);
                            }

                            card.appendChild(metaRow);
                        }

                        if (m.note) {
                            const noteP = document.createElement('p');
                            noteP.className = 'text-[11px] italic text-slate-600 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800';
                            noteP.textContent = `Catatan: ${m.note}`;
                            card.appendChild(noteP);
                        }

                        movementsContainer.appendChild(card);
                    });
                } else {
                    const emptyP = document.createElement('p');
                    emptyP.className = 'text-xs text-slate-400 text-center py-4 italic';
                    emptyP.textContent = 'Belum ada catatan riwayat pergerakan stok.';
                    movementsContainer.appendChild(emptyP);
                }

                // Show Drawer with Smooth Animation
                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });

                document.addEventListener('keydown', handleStockDrawerTrap);

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleStockDrawerTrap);

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

            // Event Delegation for Opening Stock Drawer
            document.querySelectorAll('.view-movement-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.stock-row, .stock-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                const data = JSON.parse(raw);
                                openDrawer(data);
                            } catch (e) {
                                console.error('Failed to parse stock data', e);
                            }
                        }
                    }
                };
            });

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;
        }

        initStockPage();

        if (!window.__stockListenersBound) {
            window.__stockListenersBound = true;
            document.addEventListener('DOMContentLoaded', initStockPage);
            document.addEventListener('livewire:navigated', initStockPage);
        }
    </script>
</x-layouts::app>
