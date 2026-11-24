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

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-transactions.summary-cards :metrics="$metrics" :currentFilters="$currentFilters" />

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
            <x-transactions.table :transactions="$transactions" :canEdit="$canEdit ?? false" />

            {{-- Mobile Cards View (< md) --}}
            <x-transactions.mobile-cards :transactions="$transactions" :canEdit="$canEdit ?? false" />

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
        <x-transactions.detail-drawer :canEdit="$canEdit ?? false" :canAddItems="$canAddItems ?? false" />

        {{-- ==================== 6. EDIT TRANSACTION MODAL (OWNER ONLY) ==================== --}}
        @if($canEdit ?? false)
            <x-transactions.edit-modal :paymentMethods="$paymentMethods ?? collect()" />
        @endif

        {{-- ==================== 7. ADD ITEMS TO PROCESSING ORDER MODAL ==================== --}}
        @if($canAddItems ?? false)
            <x-transactions.add-items-modal />
        @endif

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initTransactionsPage() {
            const root = document.querySelector('main[data-transactions-page="true"]');
            if (!root || root.dataset.transactionsInitialized === 'true') {
                return;
            }
            root.dataset.transactionsInitialized = 'true';

            // Flatpickr & Date Preset Sync (server-side filtering on submit)
            const filterDate = document.getElementById('filterDate');
            const flatpickrInput = document.getElementById('flatpickrDateRange');
            const filterStartDate = document.getElementById('filterStartDate');
            const filterEndDate = document.getElementById('filterEndDate');
            const clearFlatpickrBtn = document.getElementById('clearFlatpickrBtn');

            function parseYmdLocal(ymd) {
                if (!ymd || typeof ymd !== 'string') return null;
                const parts = ymd.split('-').map(Number);
                if (parts.length !== 3 || parts.some(isNaN)) return null;
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function formatYmdLocal(dateObj) {
                if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) return '';
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function updateClearBtnVisibility() {
                if (!clearFlatpickrBtn || !filterDate) return;
                if (filterDate.value === 'custom') {
                    clearFlatpickrBtn.classList.remove('hidden');
                } else {
                    clearFlatpickrBtn.classList.add('hidden');
                }
            }

            if (window.__transactionsFlatpickr) {
                try {
                    window.__transactionsFlatpickr.destroy();
                } catch (_) {}
                window.__transactionsFlatpickr = null;
            }

            let fpInstance = null;
            let isProgrammaticDateSync = false;

            if (flatpickrInput && typeof flatpickr !== 'undefined') {
                const idLocale = (flatpickr.l10ns && flatpickr.l10ns.id)
                    ? Object.assign({}, flatpickr.l10ns.id, { rangeSeparator: ' - ' })
                    : { rangeSeparator: ' - ' };

                fpInstance = flatpickr(flatpickrInput, {
                    mode: 'range',
                    dateFormat: 'd M Y',
                    locale: idLocale,
                    allowInput: false,
                    disableMobile: true,
                    onChange: function(selectedDates) {
                        if (isProgrammaticDateSync) return;
                        if (selectedDates.length >= 1) {
                            if (filterDate) {
                                filterDate.value = 'custom';
                            }
                            const startStr = formatYmdLocal(selectedDates[0]);
                            const endStr = formatYmdLocal(selectedDates[1] || selectedDates[0]);
                            if (filterStartDate) filterStartDate.value = startStr;
                            if (filterEndDate) filterEndDate.value = endStr;
                            updateClearBtnVisibility();
                        }
                    },
                    onClose: function(selectedDates, _dateStr, instance) {
                        if (isProgrammaticDateSync) return;
                        if (selectedDates.length === 1 && filterDate && filterDate.value === 'custom') {
                            const singleYmd = formatYmdLocal(selectedDates[0]);
                            if (filterStartDate) filterStartDate.value = singleYmd;
                            if (filterEndDate) filterEndDate.value = singleYmd;
                            isProgrammaticDateSync = true;
                            instance.setDate([selectedDates[0]], false);
                            isProgrammaticDateSync = false;
                        }
                    }
                });

                window.__transactionsFlatpickr = fpInstance;
            }

            function syncPresetToFlatpickr(preset, openOnCustomEmpty = false) {
                if (!fpInstance || !flatpickrInput) return;
                const todayBase = parseYmdLocal(flatpickrInput.dataset.today) || new Date();
                isProgrammaticDateSync = true;

                if (preset === 'today') {
                    fpInstance.setDate([todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === '7days') {
                    const start = new Date(todayBase);
                    start.setDate(start.getDate() - 6);
                    fpInstance.setDate([start, todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === '30days') {
                    const start = new Date(todayBase);
                    start.setDate(start.getDate() - 29);
                    fpInstance.setDate([start, todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === 'all') {
                    fpInstance.clear(false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === 'custom') {
                    const sDate = filterStartDate ? parseYmdLocal(filterStartDate.value) : null;
                    const eDate = filterEndDate ? parseYmdLocal(filterEndDate.value) : null;
                    if (sDate && eDate) {
                        if (formatYmdLocal(sDate) === formatYmdLocal(eDate)) {
                            fpInstance.setDate([sDate], false);
                        } else {
                            fpInstance.setDate([sDate, eDate], false);
                        }
                    } else if (sDate) {
                        fpInstance.setDate([sDate], false);
                    } else if (eDate) {
                        fpInstance.setDate([eDate], false);
                    } else {
                        fpInstance.clear(false);
                        if (openOnCustomEmpty) {
                            setTimeout(() => fpInstance.open(), 20);
                        }
                    }
                }

                isProgrammaticDateSync = false;
                updateClearBtnVisibility();
            }

            if (filterDate) {
                filterDate.addEventListener('change', () => {
                    syncPresetToFlatpickr(filterDate.value, true);
                });
                syncPresetToFlatpickr(filterDate.value, false);
            }

            if (clearFlatpickrBtn) {
                clearFlatpickrBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (filterDate) {
                        filterDate.value = 'today';
                    }
                    syncPresetToFlatpickr('today', false);
                });
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

            // Current active transaction data cache for drawer/edit
            let currentTrxData = null;

            // Drawer Opening & Closing Logic
            function openDrawer(data) {
                if (!drawerWrapper || !data) return;
                currentTrxData = data;

                // Populate Metadata safely
                document.getElementById('detailTrxNumber').textContent = data.transaction_number || '-';
                document.getElementById('detailSoldAt').textContent = data.sold_at || '-';
                document.getElementById('detailOutlet').textContent = data.outlet_name || '-';
                document.getElementById('detailShift').textContent = data.shift_name || '-';
                document.getElementById('detailCustomer').textContent = data.customer_name || 'Pelanggan Umum';
                const detailTableEl = document.getElementById('detailTableNumber');
                if (detailTableEl) detailTableEl.textContent = data.table_number || '-';

                // Status Badges (DOM safe)
                const payBadge = document.getElementById('detailPaymentStatusBadge');
                payBadge.textContent = '';
                const payDot = document.createElement('span');
                const payText = document.createElement('span');
                const rawPayStatus = (data.payment_status_raw || data.payment_status || '').toLowerCase();
                if (rawPayStatus === 'paid' || data.payment_status === 'Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    payText.textContent = 'Lunas';
                } else if (rawPayStatus === 'unpaid' || data.payment_status === 'Belum Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-amber-500';
                    payText.textContent = 'Belum Lunas';
                } else if (rawPayStatus === 'refund' || rawPayStatus === 'refunded' || data.payment_status === 'Refund') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-purple-50 dark:bg-purple-950/60 border border-purple-200/60 dark:border-purple-800/60 text-purple-700 dark:text-purple-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-purple-500';
                    payText.textContent = 'Refund';
                } else {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    payText.textContent = data.payment_status || '-';
                }
                payBadge.appendChild(payDot);
                payBadge.appendChild(payText);

                const txBadge = document.getElementById('detailTransactionStatusBadge');
                txBadge.textContent = '';
                const txDot = document.createElement('span');
                const txText = document.createElement('span');
                const rawTxStatus = (data.status_raw || data.status || '').toLowerCase();
                const rawOrderStatus = (data.order_status_raw || data.order_status || '').toLowerCase();
                if (data.status === 'Batal' || rawTxStatus === 'cancelled' || rawTxStatus === 'canceled' || rawTxStatus === 'batal' || rawOrderStatus === 'batal') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                    txText.textContent = 'Batal';
                } else if (data.status === 'Proses' || rawTxStatus === 'processing' || rawTxStatus === 'proses' || rawOrderStatus === 'proses') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-blue-50 dark:bg-blue-950/60 border border-blue-200/60 dark:border-blue-800/60 text-blue-700 dark:text-blue-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-blue-500';
                    txText.textContent = 'Proses';
                } else {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    txText.textContent = 'Siap';
                }
                txBadge.appendChild(txDot);
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
                        const qtyNum = Number(item.quantity);
                        const qtyStr = Number.isFinite(qtyNum)
                            ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(Math.abs(qtyNum) < 1e-7 ? 0 : qtyNum)
                            : String(item.quantity ?? '');
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
                        if (item.variant_summary) {
                            const varP = document.createElement('p');
                            varP.className = 'text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5';
                            varP.textContent = item.variant_summary;
                            infoDiv.appendChild(varP);
                        }
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

                // Show/Hide "+ Tambah Pesanan" button (only for orders still in Proses)
                const drawerAddItemsBtnEl = document.getElementById('drawerAddItemsBtn');
                if (drawerAddItemsBtnEl) {
                    const stLower = String(data.status_raw || data.status || '').toLowerCase();
                    const ordLower = String(data.order_status_raw || data.order_status || '').toLowerCase();
                    const isCancelled = ['cancelled', 'canceled', 'batal', 'dibatalkan'].includes(stLower) || ['batal', 'cancelled', 'canceled', 'dibatalkan'].includes(ordLower);
                    const isProses = !isCancelled && (['proses', 'diproses', 'masuk', 'processing'].includes(ordLower) || ['processing', 'proses'].includes(stLower));
                    if (isProses) {
                        drawerAddItemsBtnEl.classList.remove('hidden');
                    } else {
                        drawerAddItemsBtnEl.classList.add('hidden');
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

            // Drawer Print Handler
            const drawerPrintBtn = document.getElementById('drawerPrintBtn');
            if (drawerPrintBtn) {
                drawerPrintBtn.onclick = () => {
                    if (currentTrxData && currentTrxData.id) {
                        window.open(`/pos/receipt/${currentTrxData.id}`, '_blank');
                    } else if (typeof showToast === 'function') {
                        showToast('info', 'Data transaksi belum dipilih.');
                    }
                };
            }

            // ==================== EDIT MODAL LOGIC (OWNER) ====================
            const editModal = document.getElementById('editTransactionModal');
            const editBackdrop = document.getElementById('editTransactionBackdrop');
            const editDialog = document.getElementById('editTransactionDialog');
            const editForm = document.getElementById('editTransactionForm');
            const closeEditModalBtn = document.getElementById('closeEditModalBtn');
            const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
            const drawerEditBtn = document.getElementById('drawerEditBtn');
            const editPaymentMethod = document.getElementById('editPaymentMethod');
            const editPaymentStatus = document.getElementById('editPaymentStatus');
            const editStatus = document.getElementById('editStatus');
            const editCashSection = document.getElementById('editCashSection');
            const editCashReceived = document.getElementById('editCashReceived');
            const editChangeAmountDisplay = document.getElementById('editChangeAmountDisplay');
            const editNote = document.getElementById('editNote');
            const editTrxNumberDisplay = document.getElementById('editTrxNumberDisplay');
            const editTrxTotalDisplay = document.getElementById('editTrxTotalDisplay');
            const editTrxOutletTimeDisplay = document.getElementById('editTrxOutletTimeDisplay');

            let activeEditTrxTotal = 0;

            function syncCashFields() {
                if (!editCashSection || !editPaymentMethod) return;
                const method = (editPaymentMethod.value || '').toLowerCase();
                if (method === 'tunai' || method === 'cash') {
                    editCashSection.classList.remove('hidden');
                    if (!editCashReceived.value || parseFloat(editCashReceived.value) <= 0) {
                        editCashReceived.value = activeEditTrxTotal > 0 ? activeEditTrxTotal : '';
                    }
                    const received = parseFloat(editCashReceived.value || 0);
                    const change = Math.max(0, received - activeEditTrxTotal);
                    if (editChangeAmountDisplay) {
                        editChangeAmountDisplay.textContent = formatRupiah(change);
                    }
                } else {
                    editCashSection.classList.add('hidden');
                }
            }

            if (editPaymentMethod) {
                editPaymentMethod.addEventListener('change', syncCashFields);
            }
            if (editCashReceived) {
                editCashReceived.addEventListener('input', syncCashFields);
            }

            function selectEditPaymentStatus(status) {
                const input = document.getElementById('editPaymentStatus');
                if (input) input.value = status;

                document.querySelectorAll('.edit-payment-status-btn').forEach(btn => {
                    const btnStatus = btn.getAttribute('data-status');
                    const box = btn.querySelector('.edit-check-box');
                    const checkIcon = btn.querySelector('.edit-check-box i, .edit-check-box svg');

                    // Reset to neutral
                    btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 transition-all cursor-pointer';
                    if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 transition-colors';
                    if (checkIcon) checkIcon.classList.add('hidden');

                    if (btnStatus === status) {
                        if (status === 'unpaid') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-amber-500 border-amber-500 transition-colors';
                        } else if (status === 'paid') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-emerald-500 border-emerald-500 transition-colors';
                        } else if (status === 'refund') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-purple-500 bg-purple-50/70 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-purple-500 border-purple-500 transition-colors';
                        }
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    }
                });
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
            window.selectEditPaymentStatus = selectEditPaymentStatus;

            function selectEditOrderStatus(status) {
                const input = document.getElementById('editStatus');
                if (input) input.value = status;

                // When user selects Batal, ALWAYS automatically select Refund as well
                if (status === 'batal') {
                    selectEditPaymentStatus('refund');
                } else if (status === 'siap' || status === 'proses') {
                    const payStatusInput = document.getElementById('editPaymentStatus');
                    if (payStatusInput && payStatusInput.value === 'refund') {
                        selectEditPaymentStatus('paid');
                    }
                }

                document.querySelectorAll('.edit-order-status-btn').forEach(btn => {
                    const btnStatus = btn.getAttribute('data-status');
                    const box = btn.querySelector('.edit-check-box');
                    const checkIcon = btn.querySelector('.edit-check-box i, .edit-check-box svg');

                    // Reset to neutral
                    btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 transition-all cursor-pointer';
                    if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 transition-colors';
                    if (checkIcon) checkIcon.classList.add('hidden');

                    if (btnStatus === status) {
                        if (status === 'proses') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-blue-500 bg-blue-50/70 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-blue-500 border-blue-500 transition-colors';
                        } else if (status === 'siap') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-emerald-500 border-emerald-500 transition-colors';
                        } else if (status === 'batal') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-rose-500 border-rose-500 transition-colors';
                        }
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    }
                });
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
            window.selectEditOrderStatus = selectEditOrderStatus;

            function openEditModal(data) {
                if (!editModal || !data) return;
                currentTrxData = data;
                activeEditTrxTotal = Number(data.total_amount || 0);

                if (editForm) {
                    editForm.action = `/transactions/${data.id}`;
                }
                if (editTrxNumberDisplay) {
                    editTrxNumberDisplay.textContent = data.transaction_number || '';
                }
                if (editTrxTotalDisplay) {
                    editTrxTotalDisplay.textContent = formatRupiah(activeEditTrxTotal);
                }
                if (editTrxOutletTimeDisplay) {
                    editTrxOutletTimeDisplay.textContent = `${data.outlet_name || '-'} · ${data.sold_at || '-'}`;
                }

                if (editPaymentMethod) {
                    const rawMethod = (data.payment_method_raw || data.payment_method || '').toLowerCase();
                    let targetMethod = 'Tunai';
                    if (rawMethod === 'cash' || rawMethod === 'tunai') {
                        targetMethod = 'Tunai';
                    } else if (rawMethod === 'qris') {
                        targetMethod = 'QRIS';
                    } else if (rawMethod === 'transfer') {
                        targetMethod = 'Transfer';
                    } else if (rawMethod === 'card' || rawMethod === 'kartu') {
                        targetMethod = 'Kartu';
                    } else if (rawMethod === 'debit') {
                        targetMethod = 'Debit';
                    } else if (data.payment_method) {
                        targetMethod = data.payment_method;
                    }

                    // Ensure target option exists in select dropdown
                    let optionExists = false;
                    for (let i = 0; i < editPaymentMethod.options.length; i++) {
                        if (editPaymentMethod.options[i].value.toLowerCase() === targetMethod.toLowerCase()) {
                            editPaymentMethod.selectedIndex = i;
                            optionExists = true;
                            break;
                        }
                    }
                    if (!optionExists && targetMethod) {
                        const opt = document.createElement('option');
                        opt.value = targetMethod;
                        opt.textContent = targetMethod;
                        editPaymentMethod.appendChild(opt);
                        editPaymentMethod.value = targetMethod;
                    }
                }

                let targetPayStatus = 'unpaid';
                const rawPayStatus = (data.payment_status_raw || data.payment_status || '').toLowerCase();
                if (rawPayStatus === 'paid' || data.payment_status === 'Lunas') {
                    targetPayStatus = 'paid';
                } else if (rawPayStatus === 'refund' || rawPayStatus === 'refunded' || data.payment_status === 'Refund') {
                    targetPayStatus = 'refund';
                }
                selectEditPaymentStatus(targetPayStatus);

                let targetOrderStatus = 'siap';
                const rawStatus = (data.status_raw || data.status || '').toLowerCase();
                const rawOrder = (data.order_status_raw || data.order_status || '').toLowerCase();
                if (rawStatus === 'cancelled' || rawStatus === 'canceled' || rawStatus === 'batal' || rawOrder === 'batal' || data.status === 'Batal' || data.status === 'Dibatalkan') {
                    targetOrderStatus = 'batal';
                } else if (rawStatus === 'processing' || rawStatus === 'proses' || rawOrder === 'proses' || data.status === 'Proses') {
                    targetOrderStatus = 'proses';
                }
                selectEditOrderStatus(targetOrderStatus);

                if (editCashReceived) {
                    editCashReceived.value = (data.cash_received !== null && data.cash_received !== undefined) ? data.cash_received : '';
                }
                if (editNote) {
                    editNote.value = data.note || '';
                }

                syncCashFields();

                editModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    if (editBackdrop) {
                        editBackdrop.classList.remove('opacity-0');
                        editBackdrop.classList.add('opacity-100');
                    }
                    if (editDialog) {
                        editDialog.classList.remove('scale-95', 'opacity-0');
                        editDialog.classList.add('scale-100', 'opacity-100');
                    }
                });

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeEditModal() {
                if (!editModal || editModal.classList.contains('hidden')) return;

                if (editBackdrop) {
                    editBackdrop.classList.remove('opacity-100');
                    editBackdrop.classList.add('opacity-0');
                }
                if (editDialog) {
                    editDialog.classList.remove('scale-100', 'opacity-100');
                    editDialog.classList.add('scale-95', 'opacity-0');
                }

                setTimeout(() => {
                    editModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }, 200);
            }

            if (closeEditModalBtn) closeEditModalBtn.onclick = closeEditModal;
            if (cancelEditModalBtn) cancelEditModalBtn.onclick = closeEditModal;
            if (editBackdrop) editBackdrop.onclick = closeEditModal;

            if (drawerEditBtn) {
                drawerEditBtn.onclick = () => {
                    if (currentTrxData) {
                        closeDrawer();
                        setTimeout(() => {
                            openEditModal(currentTrxData);
                        }, 280);
                    }
                };
            }

            document.querySelectorAll('.edit-trx-btn').forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const rowOrCard = btn.closest('.transaction-row, .transaction-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                const data = JSON.parse(raw);
                                openEditModal(data);
                            } catch (err) {
                                console.error('Failed to parse transaction data', err);
                            }
                        }
                    }
                };
            });

            // ==================== ADD ITEMS TO PROCESSING ORDER MODAL ====================
            const addItemsModal = document.getElementById('addItemsModal');
            const addItemsBackdrop = document.getElementById('addItemsBackdrop');
            const addItemsDialog = document.getElementById('addItemsDialog');
            const closeAddItemsModalBtn = document.getElementById('closeAddItemsModalBtn');
            const cancelAddItemsModalBtn = document.getElementById('cancelAddItemsModalBtn');
            const drawerAddItemsBtn = document.getElementById('drawerAddItemsBtn');
            const addItemsSearchInput = document.getElementById('addItemsSearchInput');
            const addItemsCatalogList = document.getElementById('addItemsCatalogList');
            const addItemsCatalogCount = document.getElementById('addItemsCatalogCount');
            const addItemsSelectedList = document.getElementById('addItemsSelectedList');
            const addItemsSelectedCount = document.getElementById('addItemsSelectedCount');
            const addItemsErrorAlert = document.getElementById('addItemsErrorAlert');
            const addItemsMarkUnpaidWrapper = document.getElementById('addItemsMarkUnpaidWrapper');
            const addItemsMarkUnpaidCheckbox = document.getElementById('addItemsMarkUnpaidCheckbox');
            const submitAddItemsBtn = document.getElementById('submitAddItemsBtn');
            const submitAddItemsBtnText = document.getElementById('submitAddItemsBtnText');

            let addItemsTargetTrx = null;
            let addItemsCatalog = [];
            let addItemsTaxEnabled = @js($taxEnabled ?? false);
            let addItemsTaxRate = @js($taxRate ?? 0);
            let addItemsCart = []; // { key, product_id, name, unit_price, quantity, variant_option_ids, variant_summary }
            let isSubmittingAddItems = false;

            function showAddItemsError(msg) {
                if (!addItemsErrorAlert) return;
                if (msg) {
                    addItemsErrorAlert.textContent = msg;
                    addItemsErrorAlert.classList.remove('hidden');
                } else {
                    addItemsErrorAlert.textContent = '';
                    addItemsErrorAlert.classList.add('hidden');
                }
            }

            function renderAddItemsSummary() {
                if (!addItemsTargetTrx) return;
                const currentTotal = Number(addItemsTargetTrx.total_amount || 0);
                const addedSubtotal = addItemsCart.reduce((sum, it) => sum + (it.unit_price * it.quantity), 0);
                const addedTax = addItemsTaxEnabled ? Math.round((addedSubtotal * addItemsTaxRate) / 100) : 0;
                const newTotal = currentTotal + addedSubtotal + addedTax;

                document.getElementById('addItemsCurrentTotalDisplay').textContent = formatRupiah(currentTotal);
                document.getElementById('addItemsAddedSubtotalDisplay').textContent = '+ ' + formatRupiah(addedSubtotal);

                const taxRow = document.getElementById('addItemsTaxRow');
                if (addItemsTaxEnabled && addedTax > 0) {
                    taxRow.classList.remove('hidden');
                    document.getElementById('addItemsTaxLabel').textContent = `Estimasi Pajak Tambahan (${addItemsTaxRate}%)`;
                    document.getElementById('addItemsAddedTaxDisplay').textContent = '+ ' + formatRupiah(addedTax);
                } else {
                    taxRow.classList.add('hidden');
                }

                document.getElementById('addItemsNewTotalDisplay').textContent = formatRupiah(newTotal);

                const totalQty = addItemsCart.reduce((sum, it) => sum + it.quantity, 0);
                if (addItemsSelectedCount) {
                    addItemsSelectedCount.textContent = `${totalQty} item`;
                }
                if (submitAddItemsBtn) {
                    submitAddItemsBtn.disabled = addItemsCart.length === 0 || isSubmittingAddItems;
                }
            }

            function renderAddItemsSelected() {
                if (!addItemsSelectedList) return;
                addItemsSelectedList.innerHTML = '';

                if (addItemsCart.length === 0) {
                    addItemsSelectedList.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Belum ada menu tambahan dipilih. Klik menu di atas untuk menambahkan.</div>';
                    renderAddItemsSummary();
                    return;
                }

                addItemsCart.forEach((item, idx) => {
                    const row = document.createElement('div');
                    row.className = 'p-3 flex items-center justify-between gap-3';

                    const info = document.createElement('div');
                    info.className = 'min-w-0 flex-1';
                    const nameEl = document.createElement('p');
                    nameEl.className = 'font-bold text-slate-900 dark:text-white text-xs truncate';
                    nameEl.textContent = item.name;
                    info.appendChild(nameEl);

                    if (item.variant_summary) {
                        const varEl = document.createElement('p');
                        varEl.className = 'text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold truncate';
                        varEl.textContent = item.variant_summary;
                        info.appendChild(varEl);
                    }

                    const priceEl = document.createElement('p');
                    priceEl.className = 'text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 tabular-nums';
                    priceEl.textContent = `${formatRupiah(item.unit_price)} × ${item.quantity} = ${formatRupiah(item.unit_price * item.quantity)}`;
                    info.appendChild(priceEl);

                    const controls = document.createElement('div');
                    controls.className = 'flex items-center gap-1.5 shrink-0';

                    const minusBtn = document.createElement('button');
                    minusBtn.type = 'button';
                    minusBtn.className = 'w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center cursor-pointer';
                    minusBtn.textContent = '−';
                    minusBtn.onclick = () => {
                        if (item.quantity > 1) {
                            item.quantity -= 1;
                        } else {
                            addItemsCart.splice(idx, 1);
                        }
                        renderAddItemsSelected();
                    };

                    const qtyBadge = document.createElement('span');
                    qtyBadge.className = 'w-7 text-center font-extrabold text-xs text-slate-900 dark:text-white tabular-nums';
                    qtyBadge.textContent = String(item.quantity);

                    const plusBtn = document.createElement('button');
                    plusBtn.type = 'button';
                    plusBtn.className = 'w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center cursor-pointer';
                    plusBtn.textContent = '+';
                    plusBtn.onclick = () => {
                        item.quantity += 1;
                        renderAddItemsSelected();
                    };

                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.className = 'ml-1 w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 font-bold text-xs flex items-center justify-center cursor-pointer';
                    delBtn.textContent = '×';
                    delBtn.title = 'Hapus item';
                    delBtn.onclick = () => {
                        addItemsCart.splice(idx, 1);
                        renderAddItemsSelected();
                    };

                    controls.appendChild(minusBtn);
                    controls.appendChild(qtyBadge);
                    controls.appendChild(plusBtn);
                    controls.appendChild(delBtn);

                    row.appendChild(info);
                    row.appendChild(controls);
                    addItemsSelectedList.appendChild(row);
                });

                renderAddItemsSummary();
            }

            function addProductToAddItemsCart(product, selectedOptions = []) {
                const optionIds = selectedOptions.map(o => o.id).sort((a, b) => a - b);
                const key = `${product.id}::${optionIds.join(',')}`;
                const priceAdj = selectedOptions.reduce((sum, o) => sum + Number(o.price_adjustment || 0), 0);
                const variantSummary = selectedOptions.map(o => o.name + (o.price_adjustment > 0 ? ` (+${formatRupiah(o.price_adjustment)})` : '')).join(', ');

                const existing = addItemsCart.find(it => it.key === key);
                if (existing) {
                    existing.quantity += 1;
                } else {
                    addItemsCart.push({
                        key,
                        product_id: product.id,
                        name: product.name,
                        unit_price: Number(product.price) + priceAdj,
                        quantity: 1,
                        variant_option_ids: optionIds,
                        variant_summary: variantSummary || null,
                    });
                }
                showAddItemsError('');
                renderAddItemsSelected();
            }

            function renderAddItemsCatalog(query = '') {
                if (!addItemsCatalogList) return;
                const q = query.trim().toLowerCase();
                const filtered = q === ''
                    ? addItemsCatalog
                    : addItemsCatalog.filter(p =>
                        (p.name || '').toLowerCase().includes(q)
                        || (p.sku || '').toLowerCase().includes(q)
                        || (p.category || '').toLowerCase().includes(q)
                    );

                if (addItemsCatalogCount) {
                    addItemsCatalogCount.textContent = `${filtered.length} menu tersedia`;
                }

                addItemsCatalogList.innerHTML = '';
                if (filtered.length === 0) {
                    addItemsCatalogList.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Menu tidak ditemukan.</div>';
                    return;
                }

                filtered.forEach(product => {
                    const row = document.createElement('div');
                    row.className = 'p-3 flex flex-col gap-2 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors';

                    const top = document.createElement('div');
                    top.className = 'flex items-center justify-between gap-3';

                    const left = document.createElement('div');
                    left.className = 'min-w-0 flex-1';

                    const nameEl = document.createElement('p');
                    nameEl.className = 'font-bold text-slate-900 dark:text-white text-xs truncate';
                    nameEl.textContent = product.name;
                    left.appendChild(nameEl);

                    const metaEl = document.createElement('p');
                    metaEl.className = 'text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap';
                    const priceSpan = document.createElement('span');
                    priceSpan.className = 'font-bold text-indigo-600 dark:text-indigo-400 tabular-nums';
                    priceSpan.textContent = formatRupiah(product.price);
                    metaEl.appendChild(priceSpan);

                    if (product.is_promo && product.base_price > product.price) {
                        const baseSpan = document.createElement('span');
                        baseSpan.className = 'line-through text-slate-400 text-[10px] tabular-nums';
                        baseSpan.textContent = formatRupiah(product.base_price);
                        metaEl.appendChild(baseSpan);
                    }

                    const catSpan = document.createElement('span');
                    catSpan.textContent = `• ${product.category}`;
                    metaEl.appendChild(catSpan);
                    left.appendChild(metaEl);

                    const hasVariants = Array.isArray(product.variant_groups) && product.variant_groups.length > 0;

                    const addBtn = document.createElement('button');
                    addBtn.type = 'button';
                    addBtn.className = 'px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-600 hover:text-white text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold text-[11px] transition-colors shrink-0 cursor-pointer';
                    addBtn.textContent = hasVariants ? 'Pilih Varian' : '+ Tambah';

                    top.appendChild(left);
                    top.appendChild(addBtn);
                    row.appendChild(top);

                    if (hasVariants) {
                        const variantBox = document.createElement('div');
                        variantBox.className = 'hidden p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700 space-y-2';

                        const selectedMap = {};
                        product.variant_groups.forEach(group => {
                            const gWrap = document.createElement('div');
                            const gLabel = document.createElement('p');
                            gLabel.className = 'text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase mb-1';
                            gLabel.textContent = group.name + (group.is_required ? ' *' : '');
                            gWrap.appendChild(gLabel);

                            const optsWrap = document.createElement('div');
                            optsWrap.className = 'flex flex-wrap gap-1.5';
                            (group.options || []).forEach(opt => {
                                const optBtn = document.createElement('button');
                                optBtn.type = 'button';
                                optBtn.className = 'px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px] font-semibold text-slate-700 dark:text-slate-200 cursor-pointer transition-colors';
                                optBtn.textContent = opt.name + (opt.price_adjustment > 0 ? ` (+${formatRupiah(opt.price_adjustment)})` : '');
                                optBtn.onclick = () => {
                                    if (group.selection_type === 'multiple') {
                                        selectedMap[group.id] = selectedMap[group.id] || [];
                                        const existsIdx = selectedMap[group.id].findIndex(o => o.id === opt.id);
                                        if (existsIdx >= 0) {
                                            selectedMap[group.id].splice(existsIdx, 1);
                                            optBtn.classList.remove('!bg-indigo-600', '!text-white', '!border-indigo-600');
                                        } else {
                                            selectedMap[group.id].push(opt);
                                            optBtn.classList.add('!bg-indigo-600', '!text-white', '!border-indigo-600');
                                        }
                                    } else {
                                        optsWrap.querySelectorAll('button').forEach(b => b.classList.remove('!bg-indigo-600', '!text-white', '!border-indigo-600'));
                                        selectedMap[group.id] = [opt];
                                        optBtn.classList.add('!bg-indigo-600', '!text-white', '!border-indigo-600');
                                    }
                                };
                                optsWrap.appendChild(optBtn);
                            });
                            gWrap.appendChild(optsWrap);
                            variantBox.appendChild(gWrap);
                        });

                        const confirmVarBtn = document.createElement('button');
                        confirmVarBtn.type = 'button';
                        confirmVarBtn.className = 'w-full py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] cursor-pointer';
                        confirmVarBtn.textContent = '+ Tambahkan Menu dengan Varian';
                        confirmVarBtn.onclick = () => {
                            const flatOpts = Object.values(selectedMap).flat();
                            addProductToAddItemsCart(product, flatOpts);
                            variantBox.classList.add('hidden');
                        };
                        variantBox.appendChild(confirmVarBtn);

                        addBtn.onclick = () => {
                            variantBox.classList.toggle('hidden');
                        };
                        row.appendChild(variantBox);
                    } else {
                        addBtn.onclick = () => {
                            addProductToAddItemsCart(product, []);
                        };
                    }

                    addItemsCatalogList.appendChild(row);
                });
            }

            async function openAddItemsModal(data) {
                if (!addItemsModal || !data) return;
                addItemsTargetTrx = data;
                addItemsCart = [];
                showAddItemsError('');

                document.getElementById('addItemsTrxNumberDisplay').textContent = data.transaction_number || '';
                const tableStr = data.table_number ? ` • Meja ${data.table_number}` : '';
                document.getElementById('addItemsTrxMetaDisplay').textContent = `${data.customer_name || 'Walk-in Customer'}${tableStr} • ${data.outlet_name || ''}`;

                if (addItemsSearchInput) addItemsSearchInput.value = '';
                if (addItemsMarkUnpaidCheckbox) addItemsMarkUnpaidCheckbox.checked = false;

                // Show "Tandai Belum Lunas" toggle only if current order is paid
                const payStatusLower = String(data.payment_status_raw || data.payment_status || '').toLowerCase();
                if (addItemsMarkUnpaidWrapper) {
                    if (['paid', 'lunas'].includes(payStatusLower)) {
                        addItemsMarkUnpaidWrapper.classList.remove('hidden');
                    } else {
                        addItemsMarkUnpaidWrapper.classList.add('hidden');
                    }
                }

                renderAddItemsSelected();

                addItemsModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    if (addItemsBackdrop) {
                        addItemsBackdrop.classList.remove('opacity-0');
                        addItemsBackdrop.classList.add('opacity-100');
                    }
                    if (addItemsDialog) {
                        addItemsDialog.classList.remove('scale-95', 'opacity-0');
                        addItemsDialog.classList.add('scale-100', 'opacity-100');
                    }
                });

                // Fetch fresh branch-priced products
                if (addItemsCatalogList) {
                    addItemsCatalogList.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Memuat daftar menu cabang...</div>';
                }
                try {
                    const res = await fetch(`/transactions/${data.id}/addable-products`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) {
                        throw new Error(json.message || 'Gagal memuat katalog menu.');
                    }
                    addItemsCatalog = json.products || [];
                    addItemsTaxEnabled = Boolean(json.tax_enabled);
                    addItemsTaxRate = Number(json.tax_rate || 0);
                    renderAddItemsCatalog('');
                    renderAddItemsSummary();
                    if (addItemsSearchInput) addItemsSearchInput.focus();
                } catch (err) {
                    showAddItemsError(err.message || 'Gagal memuat daftar menu.');
                    if (addItemsCatalogList) {
                        addItemsCatalogList.innerHTML = '<div class="p-6 text-center text-xs text-rose-500">Gagal memuat daftar menu.</div>';
                    }
                }
            }

            function closeAddItemsModal() {
                if (!addItemsModal) return;
                if (addItemsBackdrop) {
                    addItemsBackdrop.classList.remove('opacity-100');
                    addItemsBackdrop.classList.add('opacity-0');
                }
                if (addItemsDialog) {
                    addItemsDialog.classList.remove('scale-100', 'opacity-100');
                    addItemsDialog.classList.add('scale-95', 'opacity-0');
                }
                setTimeout(() => {
                    addItemsModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }, 200);
            }

            if (addItemsSearchInput) {
                addItemsSearchInput.oninput = (e) => {
                    renderAddItemsCatalog(e.target.value);
                };
            }
            if (closeAddItemsModalBtn) closeAddItemsModalBtn.onclick = closeAddItemsModal;
            if (cancelAddItemsModalBtn) cancelAddItemsModalBtn.onclick = closeAddItemsModal;
            if (addItemsBackdrop) addItemsBackdrop.onclick = closeAddItemsModal;

            if (drawerAddItemsBtn) {
                drawerAddItemsBtn.onclick = () => {
                    if (currentTrxData) {
                        const target = currentTrxData;
                        closeDrawer();
                        setTimeout(() => {
                            openAddItemsModal(target);
                        }, 280);
                    }
                };
            }

            if (submitAddItemsBtn) {
                submitAddItemsBtn.onclick = async () => {
                    if (!addItemsTargetTrx || addItemsCart.length === 0 || isSubmittingAddItems) return;

                    isSubmittingAddItems = true;
                    submitAddItemsBtn.disabled = true;
                    if (submitAddItemsBtnText) submitAddItemsBtnText.textContent = 'Menyimpan...';
                    showAddItemsError('');

                    try {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

                        const payload = {
                            items: addItemsCart.map(it => ({
                                product_id: it.product_id,
                                quantity: it.quantity,
                                variant_option_ids: it.variant_option_ids || [],
                            })),
                        };

                        if (addItemsMarkUnpaidCheckbox && addItemsMarkUnpaidCheckbox.checked) {
                            payload.mark_unpaid = true;
                        }

                        const res = await fetch(`/transactions/${addItemsTargetTrx.id}/items`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            const firstErr = json.errors ? Object.values(json.errors).flat()[0] : null;
                            throw new Error(firstErr || json.message || 'Gagal menyimpan tambahan pesanan.');
                        }

                        if (typeof showToast === 'function') {
                            showToast('success', json.message || 'Tambahan pesanan berhasil disimpan.');
                        }

                        closeAddItemsModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 300);
                    } catch (err) {
                        showAddItemsError(err.message || 'Terjadi kesalahan saat menyimpan tambahan pesanan.');
                    } finally {
                        isSubmittingAddItems = false;
                        if (submitAddItemsBtnText) submitAddItemsBtnText.textContent = 'Simpan Tambahan Pesanan';
                        renderAddItemsSummary();
                    }
                };
            }
        }

        initTransactionsPage();

        if (!window.__transactionsListenersBound) {
            window.__transactionsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initTransactionsPage);
            document.addEventListener('livewire:navigated', initTransactionsPage);
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        }
    </script>
</x-layouts::app>
