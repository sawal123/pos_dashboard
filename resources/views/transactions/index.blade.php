@php
    $loadFixtures = require resource_path('views/transactions/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['transactions']);
@endphp

<x-layouts::app :title="'Transaksi'">
    <main id="mainContent" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

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
                        onclick="showToast('info', 'Halaman berikutnya akan tersedia setelah integrasi data.')"
                        class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center justify-center transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        2
                    </button>
                    <button
                        type="button"
                        onclick="showToast('info', 'Halaman berikutnya akan tersedia setelah integrasi data.')"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
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
            // Elements
            const searchInput = document.getElementById('searchTransactionsInput');
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

            // Format Currency Helper
            function formatRupiah(num) {
                return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
            }

            // Client-side Filter Logic
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const outlet = filterOutlet?.value || 'all';
                const payment = filterPaymentMethod?.value || 'all';
                const paymentStatus = filterPaymentStatus?.value || 'all';
                const txStatus = filterTransactionStatus?.value || 'all';

                let matchedCount = 0;

                // Filter rows (desktop) & cards (mobile)
                const checkMatch = (el) => {
                    const trxNum = (el.getAttribute('data-trx') || '').toLowerCase();
                    const customer = (el.getAttribute('data-customer') || '').toLowerCase();
                    const elOutlet = el.getAttribute('data-outlet');
                    const elPayment = el.getAttribute('data-payment');
                    const elPayStatus = el.getAttribute('data-payment-status');
                    const elTxStatus = el.getAttribute('data-status');

                    const matchSearch = !search || trxNum.includes(search) || customer.includes(search);
                    const matchOutlet = outlet === 'all' || elOutlet === outlet;
                    const matchPayment = payment === 'all' || elPayment === payment;
                    const matchPayStatus = paymentStatus === 'all' || elPayStatus === paymentStatus;
                    const matchTxStatus = txStatus === 'all' || elTxStatus === txStatus;

                    return matchSearch && matchOutlet && matchPayment && matchPayStatus && matchTxStatus;
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
            if (filterOutlet) filterOutlet.addEventListener('change', applyFilters);
            if (filterPaymentMethod) filterPaymentMethod.addEventListener('change', applyFilters);
            if (filterPaymentStatus) filterPaymentStatus.addEventListener('change', applyFilters);
            if (filterTransactionStatus) filterTransactionStatus.addEventListener('change', applyFilters);

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterOutlet) filterOutlet.value = 'all';
                    if (filterPaymentMethod) filterPaymentMethod.value = 'all';
                    if (filterPaymentStatus) filterPaymentStatus.value = 'all';
                    if (filterTransactionStatus) filterTransactionStatus.value = 'all';
                    applyFilters();
                });
            }

            // Drawer Opening & Closing Logic
            function openDrawer(data) {
                if (!drawerWrapper || !data) return;

                // Populate Metadata
                document.getElementById('detailTrxNumber').textContent = data.transaction_number;
                document.getElementById('detailSoldAt').textContent = data.sold_at;
                document.getElementById('detailOutlet').textContent = data.outlet_name;
                document.getElementById('detailShift').textContent = data.shift_name;
                document.getElementById('detailCustomer').textContent = data.customer_name || 'Pelanggan Umum';

                // Status Badges
                const payBadge = document.getElementById('detailPaymentStatusBadge');
                if (data.payment_status === 'Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    payBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span>Lunas</span>';
                } else {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    payBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span><span>Belum Lunas</span>';
                }

                const txBadge = document.getElementById('detailTransactionStatusBadge');
                if (data.status === 'Selesai') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    txBadge.innerHTML = '<span>Selesai</span>';
                } else {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    txBadge.innerHTML = '<span>Dibatalkan</span>';
                }

                // Laundry Readiness Section
                const laundrySection = document.getElementById('detailLaundrySection');
                if (data.order_status || data.estimated_completed_at) {
                    laundrySection.classList.remove('hidden');
                    document.getElementById('detailOrderStatus').textContent = data.order_status || '-';
                    document.getElementById('detailEstimatedCompletedAt').textContent = data.estimated_completed_at || '-';
                } else {
                    laundrySection.classList.add('hidden');
                }

                // Populate Items List (Supporting decimal quantities up to 3 decimals e.g. 4.250 kg)
                const itemsContainer = document.getElementById('detailItemsContainer');
                itemsContainer.innerHTML = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const itemEl = document.createElement('div');
                        itemEl.className = 'p-3 sm:p-3.5 flex items-start justify-between gap-3 text-xs';

                        // Parse quantity without integer cast
                        const qtyStr = item.quantity;
                        const unitLabel = item.unit ? ` ${item.unit}` : '';
                        const priceRate = item.pricing_unit === 'per_kg' ? `/${item.unit}` : '';

                        itemEl.innerHTML = `
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900 dark:text-white truncate">${item.product_name}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">${item.product_sku}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">${qtyStr}${unitLabel}</span> × ${formatRupiah(item.unit_price)}${priceRate}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-extrabold text-slate-900 dark:text-white tabular-nums">${formatRupiah(item.line_total)}</span>
                            </div>
                        `;
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
                document.getElementById('detailPaymentMethod').textContent = data.payment_method;
                document.getElementById('detailPaymentStatusText').textContent = data.payment_status;
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

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                }, 300);
            }

            // Event Delegation for Opening Drawer (works reliably across wire:navigate)
            document.querySelectorAll('.view-detail-btn').forEach(btn => {
                btn.onclick = () => {
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

            // Escape Key listener for Detail Drawer
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && drawerWrapper && !drawerWrapper.classList.contains('hidden')) {
                    closeDrawer();
                }
            });
        }

        initTransactionsPage();
        document.addEventListener('DOMContentLoaded', initTransactionsPage);
        document.addEventListener('livewire:navigated', initTransactionsPage);
    </script>
</x-layouts::app>
