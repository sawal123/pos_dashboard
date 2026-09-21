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
        <x-transactions.summary-cards :metrics="$metrics" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-transactions.filter-bar
            :outlets="$filterOptions['outlets']"
            :paymentMethods="$filterOptions['payment_methods']"
            :statuses="$filterOptions['statuses']"
            :currentFilters="$currentFilters"
        />

        {{-- ==================== 3. TRANSACTIONS LIST / EMPTY STATE ==================== --}}
        @if($transactions->isNotEmpty())
            {{-- Desktop Table View (>= md) --}}
            <x-transactions.table :transactions="$transactions" />

            {{-- Mobile Cards View (< md) --}}
            <x-transactions.mobile-cards :transactions="$transactions" />

            {{-- ==================== 4. PAGINATION ==================== --}}
            <div id="transactionsPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $transactions->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $transactions->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $transactions->total() }}</span>
                    transaksi
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Transaksi">
                    {{-- Previous --}}
                    @if($transactions->onFirstPage())
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs">
                            Sebelumnya
                        </span>
                    @else
                        <a href="{{ $transactions->previousPageUrl() }}"
                           class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            Sebelumnya
                        </a>
                    @endif

                    {{-- Page numbers (up to 7 windows) --}}
                    @foreach($transactions->getUrlRange(max(1, $transactions->currentPage() - 3), min($transactions->lastPage(), $transactions->currentPage() + 3)) as $page => $url)
                        @if($page === $transactions->currentPage())
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
                    @if($transactions->hasMorePages())
                        <a href="{{ $transactions->nextPageUrl() }}"
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
            {{-- Empty State --}}
            <x-transactions.empty-state :mode="($hasAnyTransactions ?? false) ? 'no-results' : 'no-data'" />
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

            // Custom date range toggle (local UI only — server-side filtering on submit)
            const filterDate = document.getElementById('filterDate');
            const customDateRangeContainer = document.getElementById('customDateRangeContainer');

            function syncCustomDateVisibility() {
                if (!customDateRangeContainer || !filterDate) return;
                if (filterDate.value === 'custom') {
                    customDateRangeContainer.classList.remove('hidden');
                } else {
                    customDateRangeContainer.classList.add('hidden');
                }
            }

            if (filterDate) {
                filterDate.addEventListener('change', syncCustomDateVisibility);
                syncCustomDateVisibility();
            }

            // Detail Drawer Elements
            const drawerWrapper = document.getElementById('transactionDrawerWrapper');
            const drawerBackdrop = document.getElementById('transactionDrawerBackdrop');
            const drawerPanel = document.getElementById('transactionDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDrawerBtn');
            const drawerCloseFooterBtn = document.getElementById('drawerCloseFooterBtn');

            let lastTriggerElement = null;

            // Format Currency Helper (Intl.NumberFormat with max 2 decimals, min 2 only if non-zero fraction)
            function formatRupiah(num) {
                const val = Number(num || 0);
                const hasFraction = Math.abs(val % 1) > 0.0001;
                const formatted = new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: hasFraction ? 2 : 0,
                    maximumFractionDigits: 2,
                }).format(val);
                return 'Rp ' + formatted;
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
                const rawPayStatus = (data.payment_status_raw || '').toLowerCase();
                if (rawPayStatus === 'paid' || data.payment_status === 'Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    payText.textContent = 'Lunas';
                } else if (rawPayStatus === 'unpaid' || data.payment_status === 'Belum Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-amber-500';
                    payText.textContent = 'Belum Lunas';
                } else {
                    // Unknown / other status: human-readable label with neutral/slate badge
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    payText.textContent = data.payment_status || '-';
                }
                payBadge.appendChild(payDot);
                payBadge.appendChild(payText);

                const txBadge = document.getElementById('detailTransactionStatusBadge');
                txBadge.textContent = '';
                const txText = document.createElement('span');
                if (data.status === 'Selesai') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    txText.textContent = 'Selesai';
                } else if (data.status === 'Dibatalkan') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    txText.textContent = 'Dibatalkan';
                } else {
                    // Neutral — do not default to Dibatalkan for unknown statuses
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    txText.textContent = data.status || '-';
                }
                txBadge.appendChild(txText);

                // Order Status Section (Conditional)
                const orderSection = document.getElementById('detailLaundrySection');
                if (data.order_status || data.estimated_completed_at) {
                    orderSection.classList.remove('hidden');
                    document.getElementById('detailOrderStatus').textContent = data.order_status || '-';
                    document.getElementById('detailEstimatedCompletedAt').textContent = data.estimated_completed_at || '-';
                } else {
                    orderSection.classList.add('hidden');
                }

                // Populate Items List
                // Supports decimal quantities (e.g. 4.250) and generic pricing_unit (kg, pcs, cup, paket)
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
                        const qtyStr = String(item.quantity ?? '');
                        const unitLabel = item.unit ? ` ${item.unit}` : '';
                        qtySpan.textContent = `${qtyStr}${unitLabel}`;

                        // pricing_unit: generic string from contract (kg, pcs, cup, paket, etc.)
                        // Show as /unit suffix when present, otherwise no suffix
                        const pricingUnit = item.pricing_unit ? String(item.pricing_unit) : '';
                        const priceRate = pricingUnit !== '' ? `/${pricingUnit}` : '';
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

            // Event Delegation for Opening Drawer
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

        if (!window.__transactionsListenersBound) {
            window.__transactionsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initTransactionsPage);
            document.addEventListener('livewire:navigated', initTransactionsPage);
        }
    </script>
</x-layouts::app>
