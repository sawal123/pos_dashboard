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
        <x-stock.summary-cards :summary="$summary" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-stock.filter-bar :categories="$categories" :filters="$filters" />

        {{-- ==================== 3. DATA LIST / EMPTY STATE ==================== --}}
        @if($items->total() > 0)
            {{-- Desktop Table View (>= md) --}}
            <x-stock.table :items="$items" />

            {{-- Mobile Cards View (< md) --}}
            <x-stock.mobile-cards :items="$items" />

            {{-- ==================== 4. PAGINATION ==================== --}}
            <div id="stockPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->total() }}</span>
                    item
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Stok">
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
            <x-stock.empty-state :mode="($hasAnyStock ?? false) ? 'no-results' : 'no-data'" />
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

            // Drawer Elements
            const drawerWrapper = document.getElementById('stockDrawerWrapper');
            const drawerBackdrop = document.getElementById('stockDrawerBackdrop');
            const drawerPanel = document.getElementById('stockDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeStockDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeStockDrawerFooterBtn');

            let lastTriggerElement = null;
            let currentAbortController = null;

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

                // Set product summary headers
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

                // Initial loading state in movements container
                const movementsContainer = document.getElementById('stockMovementsContainer');
                const hasMoreInfoEl = document.getElementById('stockMovementsHasMoreInfo');
                movementsContainer.textContent = '';
                if (hasMoreInfoEl) hasMoreInfoEl.classList.add('hidden');

                const loadingP = document.createElement('p');
                loadingP.className = 'text-xs text-slate-500 dark:text-slate-400 text-center py-6 italic';
                loadingP.textContent = 'Memuat riwayat stok...';
                movementsContainer.appendChild(loadingP);

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

                // Fetch movements asynchronously from server
                if (currentAbortController) {
                    currentAbortController.abort();
                }
                currentAbortController = new AbortController();

                const movementsUrl = `/stock/${itemData.id}/movements`;
                fetch(movementsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: currentAbortController.signal,
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    movementsContainer.textContent = '';

                    const movements = data.movements || [];
                    if (movements.length === 0) {
                        const emptyP = document.createElement('p');
                        emptyP.className = 'text-xs text-slate-400 text-center py-6 italic';
                        emptyP.textContent = 'Belum ada riwayat pergerakan stok.';
                        movementsContainer.appendChild(emptyP);
                        return;
                    }

                    movements.forEach(m => {
                        const card = document.createElement('div');
                        card.className = 'p-3 sm:p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-1.5 text-xs';

                        const headerRow = document.createElement('div');
                        headerRow.className = 'flex items-center justify-between gap-2';

                        const typeSpan = document.createElement('span');
                        typeSpan.className = 'font-bold text-slate-800 dark:text-slate-200';
                        typeSpan.textContent = m.movement_type || m.movement_type_raw || 'Pergerakan Stok';

                        const qtySpan = document.createElement('span');
                        const qtyNum = Number(m.quantity_change);
                        let qtyDisplay = String(m.quantity_change ?? '');
                        let qtyClass = 'text-slate-600 dark:text-slate-400';

                        if (qtyNum > 0) {
                            qtyClass = 'text-emerald-600 dark:text-emerald-400';
                            if (!qtyDisplay.startsWith('+')) {
                                qtyDisplay = `+${qtyDisplay}`;
                            }
                        } else if (qtyNum < 0) {
                            qtyClass = 'text-rose-600 dark:text-rose-400';
                        } else {
                            qtyClass = 'text-slate-600 dark:text-slate-400';
                        }

                        qtySpan.className = `font-extrabold tabular-nums ${qtyClass}`;
                        qtySpan.textContent = itemData.unit ? `${qtyDisplay} ${itemData.unit}` : qtyDisplay;

                        headerRow.appendChild(typeSpan);
                        headerRow.appendChild(qtySpan);

                        const balanceRow = document.createElement('div');
                        balanceRow.className = 'flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400';

                        const changeP = document.createElement('p');
                        changeP.textContent = `${m.stock_before} → ${m.stock_after} ${itemData.unit}`;

                        const dateP = document.createElement('p');
                        dateP.className = 'font-mono text-[10px] text-slate-400';
                        dateP.textContent = m.occurred_at || m.occurred_at_raw || '';

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

                    if (hasMoreInfoEl) {
                        if (data.has_more) {
                            hasMoreInfoEl.classList.remove('hidden');
                        } else {
                            hasMoreInfoEl.classList.add('hidden');
                        }
                    }
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    console.error('Failed to load stock movements', err);
                    movementsContainer.textContent = '';
                    const errorP = document.createElement('p');
                    errorP.className = 'text-xs text-rose-500 text-center py-6';
                    errorP.textContent = 'Riwayat stok tidak dapat dimuat.';
                    movementsContainer.appendChild(errorP);
                    if (hasMoreInfoEl) hasMoreInfoEl.classList.add('hidden');
                });
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                if (currentAbortController) {
                    currentAbortController.abort();
                    currentAbortController = null;
                }

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
