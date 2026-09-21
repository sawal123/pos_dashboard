@php
    $loadFixtures = require resource_path('views/transactions/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['transactions']);
@endphp

<x-layouts::app :title="'Transaksi'">
    <main id="mainContent" data-transactions-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== TRANSACTIONS HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Transaksi
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau seluruh transaksi penjualan dari setiap outlet.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                    <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                    <span>Monitoring Penjualan</span>
                </span>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-transactions.summary-cards :metrics="$fixtureData['metrics']" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-transactions.filter-bar :outlets="$fixtureData['outlets']" />

        {{-- ==================== 3. TRANSACTIONS LIST / EMPTY STATE ==================== --}}
        @if($hasData)
            {{-- Desktop Table View (>= md) --}}
            <x-transactions.table :transactions="$fixtureData['transactions']" />

            {{-- Mobile Cards View (< md) --}}
            <x-transactions.mobile-cards :transactions="$fixtureData['transactions']" />

            {{-- Filter Empty State (Shown when search/filter has no match) --}}
            <x-transactions.empty-state mode="no-results" />

            {{-- ==================== 4. PAGINATION ==================== --}}
            <div id="transactionsPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan <span id="visibleCount" class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['transactions']) }}</span> dari <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['transactions']) }}</span> transaksi
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Transaksi">
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
            <x-transactions.empty-state mode="no-data" />
        @endif

        {{-- ==================== 5. DETAIL DRAWER ==================== --}}
        <x-transactions.detail-drawer />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initTransactionsPage() {
            const root = document.querySelector('main[data-transactions-page="true"]');
            if (!root || root.dataset.transactionsInitialized === 'true') {
                return;
            }
            root.dataset.transactionsInitialized = 'true';

            // Elements
            const searchInput = document.getElementById('searchTransactionsInput');
            const filterDate = document.getElementById('filterDate');
            const customDateRangeContainer = document.getElementById('customDateRangeContainer');
            const filterStartDate = document.getElementById('filterStartDate');
            const filterEndDate = document.getElementById('filterEndDate');
            const filterOutlet = document.getElementById('filterOutlet');
            const filterPaymentMethod = document.getElementById('filterPaymentMethod');
            const filterPaymentStatus = document.getElementById('filterPaymentStatus');
            const filterTransactionStatus = document.getElementById('filterTransactionStatus');
            const resetFilterBtn = document.getElementById('resetFilterBtn');

            const rows = document.querySelectorAll('.transaction-row');
            const cards = document.querySelectorAll('.transaction-card');
            const filterEmptyState = document.getElementById('filterEmptyState');
            const desktopTable = document.getElementById('desktopTransactionsTable')?.closest('.rounded-2xl');
            const mobileContainer = document.getElementById('mobileTransactionsContainer');
            const paginationEl = document.getElementById('transactionsPagination');
            const visibleCountEl = document.getElementById('visibleCount');

            // Detail Drawer Elements
            const drawerWrapper = document.getElementById('transactionDrawerWrapper');
            const drawerBackdrop = document.getElementById('transactionDrawerBackdrop');
            const drawerPanel = document.getElementById('transactionDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDrawerBtn');
            const drawerCloseFooterBtn = document.getElementById('drawerCloseFooterBtn');

            let lastTriggerElement = null;

            // Format Currency Helper
            function formatRupiah(num) {
                return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
            }

            // Client-side Filter Logic
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const dateMode = filterDate?.value || 'all';
                const startDate = filterStartDate?.value || '';
                const endDate = filterEndDate?.value || '';
                const outlet = filterOutlet?.value || 'all';
                const payment = filterPaymentMethod?.value || 'all';
                const paymentStatus = filterPaymentStatus?.value || 'all';
                const txStatus = filterTransactionStatus?.value || 'all';

                // Toggle custom date range input visibility
                if (customDateRangeContainer) {
                    if (dateMode === 'custom') {
                        customDateRangeContainer.classList.remove('hidden');
                    } else {
                        customDateRangeContainer.classList.add('hidden');
                    }
                }

                const now = new Date();
                const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

                const checkDateMatch = (soldAtRaw) => {
                    if (dateMode === 'all') return true;
                    if (!soldAtRaw) return false;

                    const trxDateStr = soldAtRaw.slice(0, 10);
                    const trxTime = new Date(trxDateStr + 'T00:00:00').getTime();
                    const todayTime = new Date(todayStr + 'T00:00:00').getTime();
                    const diffDays = Math.round((todayTime - trxTime) / (1000 * 60 * 60 * 24));

                    if (dateMode === 'today') {
                        return diffDays === 0;
                    }

                    if (dateMode === '7days') {
                        return diffDays >= 0 && diffDays < 7;
                    }

                    if (dateMode === '30days') {
                        return diffDays >= 0 && diffDays < 30;
                    }

                    if (dateMode === 'custom') {
                        // Empty custom period does not hide all data without explanation
                        if (!startDate && !endDate) return true;

                        let minDate = startDate;
                        let maxDate = endDate;
                        if (minDate && maxDate && minDate > maxDate) {
                            [minDate, maxDate] = [maxDate, minDate];
                        }

                        if (minDate && trxDateStr < minDate) return false;
                        if (maxDate && trxDateStr > maxDate) return false;
                        return true;
                    }

                    return true;
                };

                let matchedCount = 0;

                // Filter rows (desktop) & cards (mobile)
                const checkMatch = (el) => {
                    const trxNum = (el.getAttribute('data-trx') || '').toLowerCase();
                    const customer = (el.getAttribute('data-customer') || '').toLowerCase();
                    const elOutlet = el.getAttribute('data-outlet');
                    const elPayment = el.getAttribute('data-payment');
                    const elPayStatus = el.getAttribute('data-payment-status');
                    const elTxStatus = el.getAttribute('data-status');
                    const elSoldAt = el.getAttribute('data-sold-at') || '';

                    const matchSearch = !search || trxNum.includes(search) || customer.includes(search);
                    const matchDate = checkDateMatch(elSoldAt);
                    const matchOutlet = outlet === 'all' || elOutlet === outlet;
                    const matchPayment = payment === 'all' || elPayment === payment;
                    const matchPayStatus = paymentStatus === 'all' || elPayStatus === paymentStatus;
                    const matchTxStatus = txStatus === 'all' || elTxStatus === txStatus;

                    return matchSearch && matchDate && matchOutlet && matchPayment && matchPayStatus && matchTxStatus;
                };

                rows.forEach(row => {
                    const match = checkMatch(row);
                    row.style.display = match ? '' : 'none';
                    if (match) matchedCount++;
                });

                cards.forEach(card => {
                    const match = checkMatch(card);
                    card.style.display = match ? '' : 'none';
                });

                if (visibleCountEl) {
                    visibleCountEl.textContent = matchedCount;
                }

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
            if (filterDate) filterDate.addEventListener('change', applyFilters);
            if (filterStartDate) filterStartDate.addEventListener('input', applyFilters);
            if (filterEndDate) filterEndDate.addEventListener('input', applyFilters);
            if (filterOutlet) filterOutlet.addEventListener('change', applyFilters);
            if (filterPaymentMethod) filterPaymentMethod.addEventListener('change', applyFilters);
            if (filterPaymentStatus) filterPaymentStatus.addEventListener('change', applyFilters);
            if (filterTransactionStatus) filterTransactionStatus.addEventListener('change', applyFilters);

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterDate) filterDate.value = 'all';
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                    if (customDateRangeContainer) customDateRangeContainer.classList.add('hidden');
                    if (filterOutlet) filterOutlet.value = 'all';
                    if (filterPaymentMethod) filterPaymentMethod.value = 'all';
                    if (filterPaymentStatus) filterPaymentStatus.value = 'all';
                    if (filterTransactionStatus) filterTransactionStatus.value = 'all';
                    applyFilters();
                });
            }

            // Basic Focus Trap for Drawer
            function handleDrawerTrap(e) {
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

            // Drawer Opening & Closing Logic
            function openDrawer(data) {
                if (!drawerWrapper || !data) return;

                // Populate Metadata safely
                document.getElementById('detailTrxNumber').textContent = data.transaction_number || '-';
                document.getElementById('detailSoldAt').textContent = data.sold_at || '-';
                document.getElementById('detailOutlet').textContent = data.outlet_name || '-';
                document.getElementById('detailShift').textContent = data.shift_name || '-';
                document.getElementById('detailCustomer').textContent = data.customer_name || 'Pelanggan Umum';

                // Status Badges (DOM safe)
                const payBadge = document.getElementById('detailPaymentStatusBadge');
                payBadge.textContent = '';
                const payDot = document.createElement('span');
                const payText = document.createElement('span');
                if (data.payment_status === 'Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    payText.textContent = 'Lunas';
                } else {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-amber-500';
                    payText.textContent = 'Belum Lunas';
                }
                payBadge.appendChild(payDot);
                payBadge.appendChild(payText);

                const txBadge = document.getElementById('detailTransactionStatusBadge');
                txBadge.textContent = '';
                const txText = document.createElement('span');
                if (data.status === 'Selesai') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    txText.textContent = 'Selesai';
                } else {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    txText.textContent = 'Dibatalkan';
                }
                txBadge.appendChild(txText);

                // Laundry Readiness Section
                const laundrySection = document.getElementById('detailLaundrySection');
                if (data.order_status || data.estimated_completed_at) {
                    laundrySection.classList.remove('hidden');
                    document.getElementById('detailOrderStatus').textContent = data.order_status || '-';
                    document.getElementById('detailEstimatedCompletedAt').textContent = data.estimated_completed_at || '-';
                } else {
                    laundrySection.classList.add('hidden');
                }

                // Populate Items List (Supporting decimal quantities up to 3 decimals e.g. 4.250 kg safely without innerHTML)
                const itemsContainer = document.getElementById('detailItemsContainer');
                itemsContainer.textContent = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const itemEl = document.createElement('div');
                        itemEl.className = 'p-3 sm:p-3.5 flex items-start justify-between gap-3 text-xs';

                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'min-w-0 flex-1';

                        const nameP = document.createElement('p');
                        nameP.className = 'font-bold text-slate-900 dark:text-white truncate';
                        nameP.textContent = item.product_name || '-';

                        const skuP = document.createElement('p');
                        skuP.className = 'text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5';
                        skuP.textContent = item.product_sku || '';

                        const metaP = document.createElement('p');
                        metaP.className = 'text-[11px] text-slate-500 dark:text-slate-400 mt-1';

                        const qtySpan = document.createElement('span');
                        qtySpan.className = 'font-semibold text-slate-700 dark:text-slate-300';
                        const qtyStr = String(item.quantity);
                        const unitLabel = item.unit ? ` ${item.unit}` : '';
                        qtySpan.textContent = `${qtyStr}${unitLabel}`;

                        const priceRate = item.pricing_unit === 'per_kg' ? `/${item.unit}` : '';
                        const metaText = document.createTextNode(` × ${formatRupiah(item.unit_price)}${priceRate}`);

                        metaP.appendChild(qtySpan);
                        metaP.appendChild(metaText);

                        infoDiv.appendChild(nameP);
                        infoDiv.appendChild(skuP);
                        infoDiv.appendChild(metaP);

                        const priceDiv = document.createElement('div');
                        priceDiv.className = 'text-right shrink-0';

                        const totalSpan = document.createElement('span');
                        totalSpan.className = 'font-extrabold text-slate-900 dark:text-white tabular-nums';
                        totalSpan.textContent = formatRupiah(item.line_total);

                        priceDiv.appendChild(totalSpan);

                        itemEl.appendChild(infoDiv);
                        itemEl.appendChild(priceDiv);

                        itemsContainer.appendChild(itemEl);
                    });
                }

                // Price Summary
                document.getElementById('detailSubtotal').textContent = formatRupiah(data.subtotal);

                const discountRow = document.getElementById('detailDiscountRow');
                if (data.discount_amount && data.discount_amount > 0) {
                    discountRow.classList.remove('hidden');
                    document.getElementById('detailDiscount').textContent = '- ' + formatRupiah(data.discount_amount);
                } else {
                    discountRow.classList.add('hidden');
                }

                const taxRow = document.getElementById('detailTaxRow');
                if (data.tax_amount && data.tax_amount > 0) {
                    taxRow.classList.remove('hidden');
                    document.getElementById('detailTax').textContent = '+ ' + formatRupiah(data.tax_amount);
                } else {
                    taxRow.classList.add('hidden');
                }

                document.getElementById('detailTotal').textContent = formatRupiah(data.total_amount);

                // Payment Details
                document.getElementById('detailPaymentMethod').textContent = data.payment_method || '-';
                document.getElementById('detailPaymentStatusText').textContent = data.payment_status || '-';
                document.getElementById('detailPaidAt').textContent = data.paid_at || 'Belum Dibayar';

                // Cash specific details (Only if Tunai)
                const cashSection = document.getElementById('detailCashOnlySection');
                if (data.payment_method === 'Tunai' && data.cash_received !== null) {
                    cashSection.classList.remove('hidden');
                    document.getElementById('detailCashReceived').textContent = formatRupiah(data.cash_received);
                    document.getElementById('detailChangeAmount').textContent = formatRupiah(data.change_amount);
                } else {
                    cashSection.classList.add('hidden');
                }

                // Gross Profit (secondary)
                document.getElementById('detailGrossProfit').textContent = formatRupiah(data.gross_profit);

                // Note Section (Only if note exists)
                const noteSection = document.getElementById('detailNoteSection');
                if (data.note) {
                    noteSection.classList.remove('hidden');
                    document.getElementById('detailNote').textContent = `"${data.note}"`;
                } else {
                    noteSection.classList.add('hidden');
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

                document.addEventListener('keydown', handleDrawerTrap);

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleDrawerTrap);

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

            // Event Delegation for Opening Drawer (stores trigger button for return focus)
            document.querySelectorAll('.view-detail-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.transaction-row, .transaction-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                const data = JSON.parse(raw);
                                openDrawer(data);
                            } catch (e) {
                                console.error('Failed to parse transaction data', e);
                            }
                        }
                    }
                };
            });

            // Close Drawer Handlers
            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (drawerCloseFooterBtn) drawerCloseFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;
        }

        initTransactionsPage();
        document.addEventListener('DOMContentLoaded', initTransactionsPage);
        document.addEventListener('livewire:navigated', initTransactionsPage);
    </script>
</x-layouts::app>
